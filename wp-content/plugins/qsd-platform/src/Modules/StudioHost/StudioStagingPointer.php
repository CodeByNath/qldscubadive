<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * StudioStagingPointer — the one QSD-owned, allowlisted fetch that lets
 * `StudioReleaseLock` follow WEX's published staging pointer on the staging
 * environment only, so that an Owner-approved WEX release advance (or
 * rollback to a release already seen) takes effect with no QSD code commit.
 *
 * `resolvePin()` never throws and never returns an unverified pin: outside
 * the staging environment, on any schema/shape/replay failure, or on a
 * pointer fetch/network failure with nothing durable recorded yet, it
 * returns the caller's own static fallback pin unchanged — the exact
 * Phase 1H fixed-pin behaviour, so a production deployment of this class is
 * inert by default. The only state this class persists is a durable
 * (`get_option`) record of the highest accepted pointer `sequence` and the
 * release it named, so a stale or replayed pointer can never walk the
 * active release backwards — including across a cache expiry or a process
 * restart, where a WP transient alone would not be durable enough. A
 * candidate pointer is never persisted on shape validity alone: its named
 * manifest must also pass `StudioManifestVerifier::verify()` — the same
 * digest/contract/releaseId/inventory checks `StudioReleaseLock` itself
 * applies — before the new sequence/release is accepted as the durable
 * baseline; a shape-valid but unverifiable candidate (outage or tamper)
 * leaves the retained last-known-good release untouched. A candidate's own
 * manifest integrity never substitutes for proof of its *approval chain*:
 * a sequence strictly above the stored one is only ever adopted when its
 * `previous` exactly names the currently stored sequence/releaseId/
 * manifestDigest — a gap this instance cannot independently verify (there
 * is no accessible history of past pointer states) is never silently
 * bridged, and fails closed to the retained release instead.
 */
final class StudioStagingPointer
{
    private const POINTER_URL = 'https://codebynath.github.io/WEXdesigns/studio-releases/staging-pointer.json';
    private const RELEASES_BASE = 'https://codebynath.github.io/WEXdesigns/studio-releases/';
    private const SCHEMA = 'wex-studio-staging-pointer@1';
    private const CONTRACT = 'wex-studio-host@1';
    private const ALLOWED_CHANGE = ['advance', 'rollback'];
    private const ALLOWED_KEYS = ['schema', 'contract', 'sequence', 'change', 'releaseId', 'manifestPath', 'manifestDigest', 'previous'];
    private const PREVIOUS_KEYS = ['sequence', 'releaseId', 'manifestDigest'];
    private const STATE_KEYS = ['sequence', 'releaseId', 'manifestUrl', 'manifestDigest', 'contract', 'checkedAt'];
    private const RELEASE_ID_PATTERN = '/^wex-studio-v[0-9]+-[0-9a-f]{40}$/';
    private const DIGEST_PATTERN = '/^sha384-[A-Za-z0-9+\/]{64}=*$/';
    private const OPTION_KEY = 'qsd_studio_pointer_state';

    private const TIMEOUT = 10;
    private const RECHECK_TTL = 300; // seconds; bounds both normal revalidation and outage retry rate.

    /** @var \Closure(string): array{status: ?int, body: ?string} */
    private \Closure $transport;

    /** @var \Closure(): string */
    private \Closure $environmentType;

    /**
     * @param (\Closure(string): array{status: ?int, body: ?string})|null $transport Test seam only.
     * @param (\Closure(): string)|null $environmentType Test seam only; production reads WordPress's own `wp_get_environment_type()`.
     */
    public function __construct(?\Closure $transport = null, ?\Closure $environmentType = null)
    {
        $this->transport = $transport ?? static function (string $url): array {
            // `redirection => 0`: a redirect response is treated as a failure,
            // never silently followed to a different, un-pinned origin.
            $response = wp_remote_get($url, ['timeout' => self::TIMEOUT, 'redirection' => 0]);
            if (is_wp_error($response)) {
                return ['status' => null, 'body' => null];
            }
            return ['status' => (int) wp_remote_retrieve_response_code($response), 'body' => (string) wp_remote_retrieve_body($response)];
        };

        $this->environmentType = $environmentType ?? static fn(): string => wp_get_environment_type();
    }

    /**
     * @param array{releaseId: string, manifestUrl: string, expectedManifestDigest: string, expectedContract: string} $staticFallback
     *     The Reviewer-accepted fixed pin, used verbatim whenever the pointer
     *     cannot or must not apply (wrong environment, no durable state yet
     *     and the pointer is unreachable, or a durably recorded state is
     *     unreadable/corrupted).
     * @return array{releaseId: string, manifestUrl: string, expectedManifestDigest: string, expectedContract: string}
     */
    public function resolvePin(array $staticFallback): array
    {
        if (($this->environmentType)() !== 'staging') {
            return $staticFallback;
        }

        [$status, $stored] = $this->readState();

        if ($status === 'valid' && $this->withinRecheckWindow($stored)) {
            return $this->pinFromState($stored);
        }

        $pointer = $this->fetchAndValidatePointer();

        if ($pointer === null) {
            if ($status === 'valid') {
                $this->touchRecheckedAt($stored);
                return $this->pinFromState($stored);
            }
            // True first-run outage, or an unreadable durable record: never
            // invent a baseline — serve the retained, independently verified
            // static pin until a valid pointer or record is available.
            return $staticFallback;
        }

        if ($status === 'corrupt') {
            // A pointer must never resolve/reset a durable record it cannot
            // read — that would let a replayed low-sequence pointer look like
            // a legitimate first run. Leave the record untouched for Owner/
            // Reviewer attention and serve the safe static pin meanwhile.
            return $staticFallback;
        }

        if ($status === 'valid' && $pointer['sequence'] < $stored['sequence']) {
            // Downgrade attempt — ignore it and keep serving the retained release.
            $this->touchRecheckedAt($stored);
            return $this->pinFromState($stored);
        }

        if ($status === 'valid' && $pointer['sequence'] === $stored['sequence']) {
            // An unchanged sequence must name the exact release/digest we
            // already hold; a different release claiming our own sequence
            // number is an integrity violation, never silently adopted —
            // either way there is nothing newer to move to.
            $this->touchRecheckedAt($stored);
            return $this->pinFromState($stored);
        }

        if ($status === 'valid' && !$this->chainsFromStored($pointer, $stored)) {
            // A higher sequence proves nothing on its own: only a `previous`
            // that exactly names our currently stored sequence/releaseId/
            // manifestDigest proves this candidate is a genuine, unbroken
            // continuation of what we already verified. A gap (whether a
            // legitimate multi-step skip or a forged pointer) can never be
            // independently verified from a single current-state fetch, so
            // it fails closed to the retained release rather than silently
            // bridging it.
            $this->touchRecheckedAt($stored);
            return $this->pinFromState($stored);
        }

        $candidateManifestUrl = self::RELEASES_BASE . $pointer['manifestPath'];
        $verified = StudioManifestVerifier::verify(
            $this->transport,
            $candidateManifestUrl,
            $pointer['manifestDigest'],
            $pointer['contract'],
            $pointer['releaseId']
        );

        if ($verified === null) {
            // The pointer was shape-valid but its named manifest could not
            // be verified (outage or tamper) — never strand the active
            // release on an unverified candidate; retain whatever was last
            // independently verified, if anything.
            if ($status === 'valid') {
                $this->touchRecheckedAt($stored);
                return $this->pinFromState($stored);
            }
            return $staticFallback;
        }

        $newState = [
            'sequence'       => $pointer['sequence'],
            'releaseId'      => $pointer['releaseId'],
            'manifestUrl'    => $candidateManifestUrl,
            'manifestDigest' => $pointer['manifestDigest'],
            'contract'       => $pointer['contract'],
            'checkedAt'      => time(),
        ];
        update_option(self::OPTION_KEY, $newState);
        return $this->pinFromState($newState);
    }

    /**
     * Fetches the one fixed, allowlisted pointer URL and validates it in
     * full before any field is trusted: exact schema/contract strings, an
     * exact allowlisted key set (no missing, no extra), an integer
     * `sequence`, an allowlisted `change`, a release id matching WEX's
     * published naming shape, a `manifestPath` that is both URL-safe and
     * exactly the path *derived* from that same `releaseId` (never an
     * independently supplied path), a well-formed SRI `manifestDigest`, and
     * — per WEX ADR 0025 — a `previous` that is `null` only at
     * `sequence === 1` and otherwise an object with exactly `sequence`
     * (int), `releaseId` (well-formed) and `manifestDigest` (well-formed).
     * Shape validation alone never proves `previous` actually chains to the
     * durably stored release — `resolvePin()` enforces that separately via
     * `chainsFromStored()` once a durable record exists.
     *
     * @return array{sequence: int, releaseId: string, manifestPath: string, manifestDigest: string, contract: string, previous: array{sequence:int,releaseId:string,manifestDigest:string}|null}|null
     */
    private function fetchAndValidatePointer(): ?array
    {
        $response = ($this->transport)(self::POINTER_URL);
        if ($response['status'] !== 200 || $response['body'] === null || $response['body'] === '') {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $keys = array_keys($decoded);
        if ($keys === [] || array_diff(self::ALLOWED_KEYS, $keys) !== [] || array_diff($keys, self::ALLOWED_KEYS) !== []) {
            return null;
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA || ($decoded['contract'] ?? null) !== self::CONTRACT) {
            return null;
        }
        if (!is_int($decoded['sequence'] ?? null) || $decoded['sequence'] < 1) {
            return null;
        }
        if (!in_array($decoded['change'] ?? null, self::ALLOWED_CHANGE, true)) {
            return null;
        }

        $releaseId = $decoded['releaseId'] ?? null;
        if (!is_string($releaseId) || preg_match(self::RELEASE_ID_PATTERN, $releaseId) !== 1) {
            return null;
        }

        $manifestPath = $decoded['manifestPath'] ?? null;
        if (!is_string($manifestPath) || !StudioAssetPath::isUrlSafeRelativePath($manifestPath)) {
            return null;
        }
        if ($manifestPath !== $releaseId . '/manifest.json') {
            return null;
        }

        $manifestDigest = $decoded['manifestDigest'] ?? null;
        if (!is_string($manifestDigest) || preg_match(self::DIGEST_PATTERN, $manifestDigest) !== 1) {
            return null;
        }

        $previous = $decoded['previous'] ?? null;
        if ($decoded['sequence'] === 1) {
            if ($previous !== null) {
                return null;
            }
        } elseif (!$this->isWellFormedPrevious($previous)) {
            return null;
        }

        return [
            'sequence'       => $decoded['sequence'],
            'releaseId'      => $releaseId,
            'manifestPath'   => $manifestPath,
            'manifestDigest' => $manifestDigest,
            'contract'       => $decoded['contract'],
            'previous'       => $previous,
        ];
    }

    /**
     * A candidate above the stored sequence is adopted only when its own
     * `previous` exactly names the currently stored sequence/releaseId/
     * manifestDigest — proof this candidate is a verified continuation of
     * what this instance already holds, not merely a higher number with a
     * manifest that happens to verify. `$pointer['previous']` is always a
     * well-formed object here: `$pointer['sequence'] > $stored['sequence']
     * >= 1` forces `$pointer['sequence'] > 1`, which `fetchAndValidatePointer()`
     * already required to carry an object, never `null`.
     *
     * @param array{previous: array{sequence:int,releaseId:string,manifestDigest:string}} $pointer
     * @param array{sequence:int,releaseId:string,manifestDigest:string} $stored
     */
    private function chainsFromStored(array $pointer, array $stored): bool
    {
        $previous = $pointer['previous'];
        return is_array($previous)
            && $previous['sequence'] === $stored['sequence']
            && $previous['releaseId'] === $stored['releaseId']
            && $previous['manifestDigest'] === $stored['manifestDigest'];
    }

    /** Exactly `{sequence: int>=1, releaseId: well-formed, manifestDigest: well-formed}`, no other keys. */
    private function isWellFormedPrevious(mixed $previous): bool
    {
        if (!is_array($previous)) {
            return false;
        }
        $keys = array_keys($previous);
        if (array_diff(self::PREVIOUS_KEYS, $keys) !== [] || array_diff($keys, self::PREVIOUS_KEYS) !== []) {
            return false;
        }
        if (!is_int($previous['sequence']) || $previous['sequence'] < 1) {
            return false;
        }
        if (!is_string($previous['releaseId']) || preg_match(self::RELEASE_ID_PATTERN, $previous['releaseId']) !== 1) {
            return false;
        }
        if (!is_string($previous['manifestDigest']) || preg_match(self::DIGEST_PATTERN, $previous['manifestDigest']) !== 1) {
            return false;
        }
        return true;
    }

    /**
     * @return array{0: 'absent'|'valid'|'corrupt', 1: array{sequence:int,releaseId:string,manifestUrl:string,manifestDigest:string,contract:string,checkedAt:int}|null}
     */
    private function readState(): array
    {
        $raw = get_option(self::OPTION_KEY, false);
        if ($raw === false) {
            return ['absent', null];
        }
        if (is_array($raw) && $this->isWellFormedState($raw)) {
            return ['valid', $raw];
        }
        return ['corrupt', null];
    }

    /**
     * A durable record is trusted only when it is fully well-formed, not
     * merely well-typed: an exact key set, a `releaseId`/`manifestDigest`
     * matching the same published shapes a fresh pointer must match, the
     * one allowed `contract`, and — critically — a `manifestUrl` that is
     * exactly the *derived* releases-base + `releaseId` + `/manifest.json`
     * path, never an arbitrary stored URL trusted at face value. Anything
     * else is treated as tampered, not merely absent.
     *
     * @param array<mixed, mixed> $raw
     */
    private function isWellFormedState(array $raw): bool
    {
        $keys = array_keys($raw);
        if (array_diff(self::STATE_KEYS, $keys) !== [] || array_diff($keys, self::STATE_KEYS) !== []) {
            return false;
        }
        if (!is_int($raw['sequence']) || $raw['sequence'] < 1) {
            return false;
        }
        if (!is_string($raw['releaseId']) || preg_match(self::RELEASE_ID_PATTERN, $raw['releaseId']) !== 1) {
            return false;
        }
        if (!is_string($raw['manifestDigest']) || preg_match(self::DIGEST_PATTERN, $raw['manifestDigest']) !== 1) {
            return false;
        }
        if (($raw['contract'] ?? null) !== self::CONTRACT) {
            return false;
        }
        if (!is_int($raw['checkedAt']) || $raw['checkedAt'] < 0) {
            return false;
        }
        if (!is_string($raw['manifestUrl']) || $raw['manifestUrl'] !== self::RELEASES_BASE . $raw['releaseId'] . '/manifest.json') {
            return false;
        }
        return true;
    }

    /** @param array{checkedAt:int} $state */
    private function withinRecheckWindow(array $state): bool
    {
        return (time() - $state['checkedAt']) < self::RECHECK_TTL;
    }

    /** @param array{sequence:int,releaseId:string,manifestUrl:string,manifestDigest:string,contract:string,checkedAt:int} $state */
    private function touchRecheckedAt(array $state): void
    {
        $state['checkedAt'] = time();
        update_option(self::OPTION_KEY, $state);
    }

    /**
     * @param array{releaseId:string,manifestUrl:string,manifestDigest:string,contract:string} $state
     * @return array{releaseId: string, manifestUrl: string, expectedManifestDigest: string, expectedContract: string}
     */
    private function pinFromState(array $state): array
    {
        return [
            'releaseId'              => $state['releaseId'],
            'manifestUrl'            => $state['manifestUrl'],
            'expectedManifestDigest' => $state['manifestDigest'],
            'expectedContract'       => $state['contract'],
        ];
    }
}
