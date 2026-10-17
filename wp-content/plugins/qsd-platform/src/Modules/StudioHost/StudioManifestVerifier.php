<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * The one shared manifest fetch-and-verify routine `StudioReleaseLock` and
 * `StudioStagingPointer` both call, so a pointer-driven candidate release is
 * held to exactly the same digest/contract/releaseId/inventory checks as the
 * fixed pin before either persists or enqueues anything from it. Fails
 * closed to `null` on a non-https/host-less URL, a network error, a non-200
 * response, a manifest-body digest mismatch, an unexpected `contract`/
 * `releaseId`, a missing/empty/malformed `files` digest inventory, or an
 * `entry`/`styles` path absent from that inventory.
 */
final class StudioManifestVerifier
{
    /**
     * @param \Closure(string): array{status: ?int, body: ?string} $transport
     * @return array{releaseId: string, entry: string, styles: string[], files: array<string, string>}|null
     */
    public static function verify(
        \Closure $transport,
        string $manifestUrl,
        string $expectedDigest,
        string $expectedContract,
        string $expectedReleaseId
    ): ?array {
        if (parse_url($manifestUrl, PHP_URL_SCHEME) !== 'https' || !is_string(parse_url($manifestUrl, PHP_URL_HOST))) {
            return null;
        }

        $response = $transport($manifestUrl);
        if ($response['status'] !== 200 || $response['body'] === null || $response['body'] === '') {
            return null;
        }

        $actualDigest = 'sha384-' . base64_encode(hash('sha384', $response['body'], true));
        if (!hash_equals($expectedDigest, $actualDigest)) {
            return null;
        }

        $manifest = json_decode($response['body'], true);
        if (!is_array($manifest)) {
            return null;
        }
        if (($manifest['contract'] ?? null) !== $expectedContract) {
            return null;
        }
        if (($manifest['releaseId'] ?? null) !== $expectedReleaseId) {
            return null;
        }

        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : null;
        if ($files === null || !self::isValidDigestInventory($files)) {
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
            'releaseId' => $expectedReleaseId,
            'entry'     => $entry,
            'styles'    => array_values($styles),
            'files'     => $files,
        ];
    }

    /**
     * Every inventory key must pass the same URL-safety check
     * `StudioAssetGateway` already requires of a manifest path — no
     * `..`/`.` segment, no leading slash, no Windows drive letter, no `%` —
     * since each key is concatenated onto a verified base URL to build a
     * browser-facing asset URL. Every value must be a well-formed
     * `sha384-<base64>` SRI digest string.
     *
     * @param array<mixed, mixed> $files
     */
    private static function isValidDigestInventory(array $files): bool
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
