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
 * restart, where a WP transient alone would not be durable enough.
 */
final class StudioStagingPointer
{
    private const POINTER_URL = 'https://codebynath.github.io/WEXdesigns/studio-releases/staging-pointer.json';
    private const RELEASES_BASE = 'https://codebynath.github.io/WEXdesigns/studio-releases/';
    private const SCHEMA = 'wex-studio-staging-pointer@1';
    private const CONTRACT = 'wex-studio-host@1';
    private const ALLOWED_CHANGE = ['advance', 'rollback'];
    private const ALLOWED_KEYS = ['schema', 'contract', 'sequence', 'change', 'releaseId', 'manifestPath', 'manifestDigest', 'previous'];
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

        if ($status === 'valid' && $pointer['sequence'] <= $stored['sequence']) {
            // Replay or downgrade attempt (or an unchanged pointer already
            // adopted) — ignore it and keep serving the retained release.
            $this->touchRecheckedAt($stored);
            return $this->pinFromState($stored);
        }

        $newState = [
            'sequence'       => $pointer['sequence'],
            'releaseId'      => $pointer['releaseId'],
            'manifestUrl'    => self::RELEASES_BASE . $pointer['manifestPath'],
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
     * a `previous` that is either `null` or itself a well-formed release id.
     * `previous` is validated for shape only, never required to chain to the
     * durably stored release — a legitimately skipped intermediate sequence
     * means `previous` will not always match what this instance last saw.
     *
     * @return array{sequence: int, releaseId: string, manifestPath: string, manifestDigest: string, contract: string}|null
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
        if ($previous !== null && (!is_string($previous) || preg_match(self::RELEASE_ID_PATTERN, $previous) !== 1)) {
            return null;
        }

        return [
            'sequence'       => $decoded['sequence'],
            'releaseId'      => $releaseId,
            'manifestPath'   => $manifestPath,
            'manifestDigest' => $manifestDigest,
            'contract'       => $decoded['contract'],
        ];
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
        if (
            is_array($raw)
            && is_int($raw['sequence'] ?? null)
            && is_string($raw['releaseId'] ?? null)
            && is_string($raw['manifestUrl'] ?? null)
            && is_string($raw['manifestDigest'] ?? null)
            && is_string($raw['contract'] ?? null)
            && is_int($raw['checkedAt'] ?? null)
        ) {
            return ['valid', $raw];
        }
        return ['corrupt', null];
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
