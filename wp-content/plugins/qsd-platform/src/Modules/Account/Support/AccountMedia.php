<?php

namespace QSD\Platform\Modules\Account\Support;

/**
 * AccountMedia — Account-owned Brand media (logo/favicon) storage.
 *
 * Content-hash-addressed: the stored filename is the SHA-256 of the file's
 * bytes plus its sniffed extension, so re-uploading the same image is a
 * no-op and nothing already stored is ever deleted (no uncontrolled
 * cleanup — a media id may still be referenced by the catalogue or a prior
 * draft). Not the WordPress Media Library: no attachment post, no shared
 * `wp_media` surface.
 *
 * Validation never trusts the filename or a claimed MIME type: the real
 * image type is sniffed from content via getimagesizefromstring(). Upload
 * origin is verified via is_uploaded_file() before any byte is read, and
 * size is checked on disk before the file is read into memory.
 */
final class AccountMedia
{
    public const DIRECTORY = 'qsd-account';
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> sniffed MIME type => stored extension */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    private \Closure $isUploadedFile;

    /** @param callable(string): bool|null $isUploadedFile Test seam only; production uses is_uploaded_file(). */
    public function __construct(?callable $isUploadedFile = null)
    {
        $this->isUploadedFile = $isUploadedFile === null
            ? static fn(string $path): bool => is_uploaded_file($path)
            : \Closure::fromCallable($isUploadedFile);
    }

    /**
     * @param array{tmp_name?: string} $file one entry of $request->get_file_params()
     * @return array{media_id: string, mime: string, size: int}|\WP_Error
     */
    public function store(array $file): array|\WP_Error
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !($this->isUploadedFile)($tmpName)) {
            return new \WP_Error('qsd_account_media_invalid', 'No uploaded file was received.');
        }

        $size = filesize($tmpName);
        if ($size === false || $size <= 0) {
            return new \WP_Error('qsd_account_media_invalid', 'The uploaded file is empty or unreadable.');
        }
        if ($size > self::MAX_BYTES) {
            return new \WP_Error('qsd_account_media_too_large', 'The image must be 5 MB or smaller.');
        }

        $bytes = file_get_contents($tmpName);
        if ($bytes === false) {
            return new \WP_Error('qsd_account_media_invalid', 'The uploaded file could not be read.');
        }

        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return new \WP_Error('qsd_account_media_type', 'Only JPEG, PNG, GIF, or WebP images are accepted.');
        }

        $mediaId = hash('sha256', $bytes) . '.' . self::ALLOWED_TYPES[$mime];
        $path    = $this->directory() . '/' . $mediaId;

        if (!is_file($path)) {
            $staged = $path . '.' . uniqid('upload', true);
            if (file_put_contents($staged, $bytes) === false || !rename($staged, $path)) {
                if (is_file($staged)) {
                    unlink($staged);
                }
                return new \WP_Error('qsd_account_media_storage', 'The image could not be stored.');
            }
        }

        return ['media_id' => $mediaId, 'mime' => $mime, 'size' => $size];
    }

    public function url(string $mediaId): string
    {
        $upload = wp_upload_dir();

        return trailingslashit((string) $upload['baseurl']) . self::DIRECTORY . '/' . $mediaId;
    }

    private function directory(): string
    {
        $upload = wp_upload_dir();
        $dir    = trailingslashit((string) $upload['basedir']) . self::DIRECTORY;
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }
}
