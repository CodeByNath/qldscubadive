<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * StudioReleaseLock — the QSD-owned pin for exactly one approved, immutable
 * WEX Studio release, fetched over HTTPS from WEX's own published release
 * origin and verified end-to-end before anything from it is trusted.
 *
 * The pin below (release id, manifest URL, expected manifest digest,
 * expected contract) is committed here by a human reviewing a WEX closeout
 * record — never supplied by a request, an option, or any other runtime
 * input. Moving the pin to a previously approved release, or to a newly
 * approved one, is a one-line constant edit and redeploy; it never requires
 * cloning WEX, building, or copying any asset into this repository.
 *
 * Verification is fail-closed at every step. A network error, a non-200
 * response, a manifest digest mismatch, an unexpected contract or release
 * id, a malformed or empty digest inventory, or an entry/style path not
 * present in that inventory all resolve to `null` — the one "nothing to
 * enqueue yet" state `StudioAssetGateway` already handles for a missing
 * local manifest. Nothing here ever enqueues an asset whose digest was not
 * itself confirmed against the pinned, signed-off manifest.
 *
 * When no explicit `$pin` is given (production), the active pin is resolved
 * once, at construction, through `StudioStagingPointer`: on the staging
 * environment only, that collaborator may follow WEX's published staging
 * pointer to a newer (or previously retained) approved release with no code
 * change; everywhere else, and on any pointer failure with nothing durable
 * recorded yet, it returns the four constants below unchanged. An explicit
 * `$pin` (test seam) always bypasses the pointer entirely.
 */
final class StudioReleaseLock
{
    // QSD-owned pin. Edit all four together, only from a reviewed WEX
    // closeout record (release id, manifest URL, manifest digest, contract).
    private const RELEASE_ID = 'wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d';
    private const MANIFEST_URL = 'https://codebynath.github.io/WEXdesigns/studio-releases/wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d/manifest.json';
    private const EXPECTED_MANIFEST_DIGEST = 'sha384-TGJL9R8YXT5xSlCTXoBG8NtbtuBnbXUKEZiqzlU5MBnGuoIgoREBquPzbO7Cob7W';
    private const EXPECTED_CONTRACT = 'wex-studio-host@1';

    private const TIMEOUT = 10;
    private const CACHE_TTL = 300; // seconds; upstream's own Cache-Control is max-age=600.

    private string $releaseId;
    private string $manifestUrl;
    private string $expectedManifestDigest;
    private string $expectedContract;

    /** @var \Closure(string): array{status: ?int, body: ?string} */
    private \Closure $transport;

    /**
     * @param array{releaseId: string, manifestUrl: string, expectedManifestDigest: string, expectedContract: string}|null $pin
     *     Test seam only, and the mechanism by which switching the active
     *     release (forward or back to a retained approved one) is proven to
     *     need no asset copy. When omitted (production), the active pin
     *     comes from `$pointer` instead of the fixed constants directly.
     * @param (\Closure(string): array{status: ?int, body: ?string})|null $transport Test seam only.
     * @param StudioStagingPointer|null $pointer Test seam only; ignored whenever `$pin` is given explicitly.
     */
    public function __construct(?array $pin = null, ?\Closure $transport = null, ?StudioStagingPointer $pointer = null)
    {
        $resolved = $pin ?? ($pointer ?? new StudioStagingPointer())->resolvePin([
            'releaseId'              => self::RELEASE_ID,
            'manifestUrl'            => self::MANIFEST_URL,
            'expectedManifestDigest' => self::EXPECTED_MANIFEST_DIGEST,
            'expectedContract'       => self::EXPECTED_CONTRACT,
        ]);

        $this->releaseId              = $resolved['releaseId'];
        $this->manifestUrl            = $resolved['manifestUrl'];
        $this->expectedManifestDigest = $resolved['expectedManifestDigest'];
        $this->expectedContract       = $resolved['expectedContract'];

        $this->transport = $transport ?? static function (string $url): array {
            $response = wp_remote_get($url, ['timeout' => self::TIMEOUT]);
            if (is_wp_error($response)) {
                return ['status' => null, 'body' => null];
            }
            return ['status' => (int) wp_remote_retrieve_response_code($response), 'body' => (string) wp_remote_retrieve_body($response)];
        };
    }

    /**
     * The verified, ready-to-enqueue release, or `null` when any part of it
     * could not be confirmed. A cached result is only ever one this same
     * method already verified in full — never a result cached before
     * verification, and never served once the active pin's release id no
     * longer matches the cache entry's.
     *
     * @return array{releaseId: string, entry: string, styles: string[], files: array<string, string>}|null
     */
    public function verifiedRelease(): ?array
    {
        $cacheKey = $this->cacheKey();
        $cached   = get_transient($cacheKey);
        if (is_array($cached) && ($cached['releaseId'] ?? null) === $this->releaseId) {
            return $cached;
        }

        $verified = $this->fetchAndVerify();
        if ($verified !== null) {
            set_transient($cacheKey, $verified, self::CACHE_TTL);
        }
        return $verified;
    }

    /** The browser-facing URL for a validated relative path from the verified release's digest inventory. */
    public function assetUrl(string $relative): string
    {
        $base = substr($this->manifestUrl, 0, (int) strrpos($this->manifestUrl, '/') + 1);
        return $base . $relative;
    }

    private function cacheKey(): string
    {
        return 'qsd_studio_release_' . substr(hash('sha256', $this->manifestUrl), 0, 20);
    }

    /** @return array{releaseId: string, entry: string, styles: string[], files: array<string, string>}|null */
    private function fetchAndVerify(): ?array
    {
        if (!$this->isApprovedManifestUrl($this->manifestUrl)) {
            return null;
        }

        $response = ($this->transport)($this->manifestUrl);
        if ($response['status'] !== 200 || $response['body'] === null || $response['body'] === '') {
            return null;
        }

        $actualDigest = 'sha384-' . base64_encode(hash('sha384', $response['body'], true));
        if (!hash_equals($this->expectedManifestDigest, $actualDigest)) {
            return null;
        }

        $manifest = json_decode($response['body'], true);
        if (!is_array($manifest)) {
            return null;
        }
        if (($manifest['contract'] ?? null) !== $this->expectedContract) {
            return null;
        }
        if (($manifest['releaseId'] ?? null) !== $this->releaseId) {
            return null;
        }

        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : null;
        if ($files === null || !$this->isValidDigestInventory($files)) {
            return null;
        }

        $entry  = isset($manifest['entry']) ? (string) $manifest['entry'] : '';
        $styles = is_array($manifest['styles'] ?? null) ? $manifest['styles'] : [];

        if ($entry === '' || !array_key_exists($entry, $files)) {
            return null;
        }
        foreach ($styles as $style) {
            if (!is_string($style) || !array_key_exists($style, $files)) {
                return null;
            }
        }

        return [
            'releaseId' => $this->releaseId,
            'entry'     => $entry,
            'styles'    => array_values($styles),
            'files'     => $files,
        ];
    }

    /**
     * https, and exactly the pinned host + path — never a different origin,
     * a mutable `main`/`latest` alias, or a value built from anything other
     * than this lock's own constructor-fixed pin.
     */
    private function isApprovedManifestUrl(string $url): bool
    {
        return $url === $this->manifestUrl
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string(parse_url($url, PHP_URL_HOST));
    }

    /**
     * Every inventory key must pass the same URL-safety check
     * `StudioAssetGateway` already requires of a manifest path — no
     * `..`/`.` segment, no leading slash, no Windows drive letter, no `%` —
     * since each key is concatenated onto this lock's fixed, verified base
     * URL to build a browser-facing asset URL; an unsafe key could
     * otherwise walk that URL's path out of the pinned release directory
     * while staying on the same approved origin. Every value must be a
     * well-formed `sha384-<base64>` SRI digest string, exactly the form the
     * browser's own `integrity` attribute expects, so a value that passes
     * here is already usable verbatim.
     *
     * @param array<mixed, mixed> $files
     */
    private function isValidDigestInventory(array $files): bool
    {
        if ($files === []) {
            return false;
        }
        foreach ($files as $path => $digest) {
            if (!is_string($path) || !StudioAssetPath::isUrlSafeRelativePath($path)) {
                return false;
            }
            if (!is_string($digest) || preg_match('/^sha384-[A-Za-z0-9+\/]{64}=*$/', $digest) !== 1) {
                return false;
            }
        }
        return true;
    }
}
