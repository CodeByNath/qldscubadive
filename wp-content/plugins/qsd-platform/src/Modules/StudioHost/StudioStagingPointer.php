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
 * `resolvePin()` itself never skips this fetch-and-validate pass on a
 * cached/recent result: per Owner directive, the recheck interval is zero
 * seconds, so every call re-fetches and re-validates the pointer fresh.
 *
 * The staging gate itself is never inferred from a browser URL/Host header
 * or any request input: it is `wp_get_environment_type() === 'staging'` —
 * Owner-set in `wp-config.php` — OR a deployment-proven marker this same
 * CI pipeline's `deploy-staging` job writes straight to the server over its
 * existing SSH deployment capability right after copying files, naming the
 * exact commit and branch it deployed. Neither signal requires the Owner to
 * edit WordPress config or sign in to wp-admin; a server with neither is
 * inert by construction, identical to the original fixed-pin behaviour.
 *
 * A candidate pointer is never persisted on shape validity alone: its named
 * manifest must also pass `StudioManifestVerifier::verify()` — the same
 * digest/contract/releaseId/inventory checks `StudioReleaseLock` itself
 * applies — before the new sequence/release is accepted as the durable
 * baseline; a shape-valid but unverifiable candidate (outage or tamper)
 * leaves the retained last-known-good release untouched. Per Owner
 * directive (18 October 2026, Phase 1L), a strictly higher sequence is
 * adopted on manifest verification alone: this consumer no longer requires
 * its `previous` to chain to the currently stored release, so an instance
 * that missed one or more intermediate published sequences still catches
 * up to the latest verified release on its next check, rather than
 * remaining permanently stuck. Replay/downgrade protection is unchanged —
 * a lower sequence, or an equal sequence naming a different release, is
 * still rejected outright; only a strictly higher sequence skips the
 * chain requirement. WEX's own ADR 0025 publishing-side `previous`
 * contract is unchanged; only this consumer's acceptance rule for it was
 * relaxed.
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

    private const DEPLOY_MARKER_KEYS = ['deployedBy', 'branch', 'sha', 'deployedAt'];
    private const DEPLOY_MARKER_BY = 'qsd-platform-ci';
    private const DEPLOY_MARKER_BRANCH = 'staging';
    private const DEPLOY_MARKER_SHA_PATTERN = '/^[0-9a-f]{40}$/';
    private const DEPLOY_MARKER_TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const TIMEOUT = 10;

    /** @var \Closure(string): array{status: ?int, body: ?string} */
    private \Closure $transport;

    /** @var \Closure(): string */
    private \Closure $environmentType;

    /** @var \Closure(): ?array */
    private \Closure $deployMarker;

    /**
     * @param (\Closure(string): array{status: ?int, body: ?string})|null $transport Test seam only.
     * @param (\Closure(): string)|null $environmentType Test seam only; production reads WordPress's own `wp_get_environment_type()`.
     * @param (\Closure(): ?array)|null $deployMarker Test seam only; production reads the CI-written deployment-proof marker file.
     */
    public function __construct(?\Closure $transport = null, ?\Closure $environmentType = null, ?\Closure $deployMarker = null)
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

        $this->deployMarker = $deployMarker ?? static function (): ?array {
            if (!defined('WP_CONTENT_DIR')) {
                return null;
            }
            $path = WP_CONTENT_DIR . '/qsd-studio-staging-deploy.json';
            if (!is_file($path)) {
                return null;
            }
            $contents = @file_get_contents($path);
            if (!is_string($contents) || $contents === '') {
                return null;
            }
            $decoded = json_decode($contents, true);
            return is_array($decoded) ? $decoded : null;
        };
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
        if (!$this->isStagingGateActive()) {
            return $staticFallback;
        }

        [$status, $stored] = $this->readState();

        // Owner-directed: zero-second recheck — every call re-fetches and
        // re-validates the pointer, never a cached/skipped check. No TTL,
        // transient, or invented debounce stands in for this.
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

        // Per Owner directive (Phase 1L), a strictly higher sequence is no
        // longer required to chain its `previous` to the currently stored
        // release — WEX's sequential publishing plus full manifest
        // verification below is treated as sufficient proof on its own, so
        // a skipped intermediate sequence no longer leaves this consumer
        // permanently stuck on the last release it directly chained from.

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
     * Non-secret, read-only description of this instance's current gate
     * state, for deployment diagnostics only (`StudioStagingDiagnostic`) —
     * never consulted by `resolvePin()` itself, which calls
     * `isStagingGateActive()` directly.
     *
     * @return array{environmentType: string, deploymentProven: bool, active: bool}
     */
    public function describeGate(): array
    {
        $environmentType = ($this->environmentType)();
        $deploymentProven = $this->isDeploymentProvenStaging();
        return [
            'environmentType'  => $environmentType,
            'deploymentProven' => $deploymentProven,
            'active'           => $environmentType === 'staging' || $deploymentProven,
        ];
    }

    /**
     * The highest durably accepted sequence, or `null` when no well-formed
     * durable record exists. Diagnostics only (the published sequence is
     * not secret); `resolvePin()` never calls this — it reads `readState()`
     * directly.
     */
    public function storedSequence(): ?int
    {
        [$status, $stored] = $this->readState();
        return $status === 'valid' ? $stored['sequence'] : null;
    }

    /**
     * A genuinely non-mutating read of the durable record alone: calls
     * `readState()` (one `get_option()`, nothing else) and returns a
     * sanitized view of it — no outbound pointer fetch, no manifest
     * verification, and no `update_option()` call on any path, unlike
     * `resolvePin()`. Built for the Phase 1K read-only CLI diagnostic,
     * which must inspect current state without being able to advance,
     * replay-protect-touch, or otherwise write it.
     *
     * @return array{state: 'absent'|'valid'|'corrupt', sequence: ?int, releaseId: ?string, manifestDigest: ?string, contract: ?string, checkedAt: ?int}
     */
    public function inspectStoredState(): array
    {
        [$status, $stored] = $this->readState();
        if ($status !== 'valid') {
            return ['state' => $status, 'sequence' => null, 'releaseId' => null, 'manifestDigest' => null, 'contract' => null, 'checkedAt' => null];
        }
        return [
            'state'          => 'valid',
            'sequence'       => $stored['sequence'],
            'releaseId'      => $stored['releaseId'],
            'manifestDigest' => $stored['manifestDigest'],
            'contract'       => $stored['contract'],
            'checkedAt'      => $stored['checkedAt'],
        ];
    }

    /** Never inferred from a browser URL/Host header or any request input — see class docblock. */
    private function isStagingGateActive(): bool
    {
        return ($this->environmentType)() === 'staging' || $this->isDeploymentProvenStaging();
    }

    /**
     * True only when the CI-written marker at `$deployMarker` is exactly
     * `{deployedBy: 'qsd-platform-ci', branch: 'staging', sha: <40-hex>,
     * deployedAt: <UTC ISO-8601>}` — proof this exact server received a
     * `staging`-branch deploy from this pipeline's own SSH step, never a
     * value an attacker or a generic file copy could produce by accident.
     */
    private function isDeploymentProvenStaging(): bool
    {
        $marker = ($this->deployMarker)();
        if (!is_array($marker)) {
            return false;
        }

        $keys = array_keys($marker);
        if (array_diff(self::DEPLOY_MARKER_KEYS, $keys) !== [] || array_diff($keys, self::DEPLOY_MARKER_KEYS) !== []) {
            return false;
        }

        if (($marker['deployedBy'] ?? null) !== self::DEPLOY_MARKER_BY) {
            return false;
        }
        if (($marker['branch'] ?? null) !== self::DEPLOY_MARKER_BRANCH) {
            return false;
        }
        if (!is_string($marker['sha'] ?? null) || preg_match(self::DEPLOY_MARKER_SHA_PATTERN, $marker['sha']) !== 1) {
            return false;
        }
        if (!is_string($marker['deployedAt'] ?? null) || preg_match(self::DEPLOY_MARKER_TIMESTAMP_PATTERN, $marker['deployedAt']) !== 1) {
            return false;
        }

        return true;
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
