<?php

namespace QSD\Platform\Modules\StudioHost;

/**
 * StudioStorage — the host-private writable root for WEX's own
 * identity-space storage, isolated from every QSD business record.
 *
 * The root is one directory above the WordPress install itself
 * (`dirname(ABSPATH)`), never under `wp_upload_dir()` or anywhere inside
 * `ABSPATH`. A web server's document root is always configured to the
 * WordPress install directory or somewhere under it; a path one level above
 * that is outside every URL a visitor can request, on Apache, Nginx, or
 * anything else — a stronger, server-config-independent guarantee than
 * `.htaccess` alone, which Nginx does not read at all. The `.htaccess`/
 * `index.php` pair is still written as cheap defence in depth on servers
 * that do honour it, but the real boundary is the filesystem location.
 * `ensureProtectedRoot()` fails closed (returns false) if either file
 * cannot actually be written, rather than silently proceeding unprotected.
 *
 * First initialization is an explicit administrator-approved runtime action
 * (`initialize()`, called only from the REST `POST /studio/storage/init`
 * route) — nothing here runs it automatically, so a page load alone never
 * provisions it. Every operation is fail-closed: an unwritable root, a
 * missing initialization, or an invalid payload returns false/null rather
 * than throwing or silently proceeding.
 *
 * The one stored resource is a single identity-space JSON blob
 * (`identity.json`) — not a generic path-addressable file API. WEX's host
 * business identity is never created here; QSD's own `PlatformIdentifierStation`
 * is untouched by this class.
 */
class StudioStorage
{
    private const DIR_NAME  = 'qsd-studio-private';
    private const MARKER    = '.initialized';
    private const IDENTITY  = 'identity.json';
    private const MAX_BYTES = 1_048_576;

    public function root(): string
    {
        $wpRoot = defined('ABSPATH') ? rtrim((string) ABSPATH, '/\\') : '';
        return $wpRoot === '' ? '' : dirname($wpRoot) . '/' . self::DIR_NAME;
    }

    public function isInitialized(): bool
    {
        $root = $this->root();
        return $root !== '' && is_file($root . '/' . self::MARKER);
    }

    /**
     * Idempotent: an already-initialized root is never re-stamped or reset.
     * Fail-closed: returns false, never throws, when the root cannot be made
     * writable.
     */
    public function initialize(int $userId): bool
    {
        if ($this->isInitialized()) {
            return true;
        }
        if (!$this->ensureProtectedRoot()) {
            return false;
        }

        $marker = wp_json_encode([
            'initializedAt' => time(),
            'initializedBy' => $userId,
        ]);

        return @file_put_contents($this->root() . '/' . self::MARKER, (string) $marker) !== false;
    }

    /** Null when uninitialized or nothing has been written yet. */
    public function readIdentity(): ?string
    {
        if (!$this->isInitialized()) {
            return null;
        }
        $path = $this->root() . '/' . self::IDENTITY;
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /** Fail-closed: false without writing when uninitialized, invalid JSON, or oversized. */
    public function writeIdentity(string $json): bool
    {
        if (!$this->isInitialized()) {
            return false;
        }
        if (strlen($json) > self::MAX_BYTES) {
            return false;
        }
        if ($json !== 'null' && json_decode($json) === null) {
            return false;
        }

        return @file_put_contents($this->root() . '/' . self::IDENTITY, $json) !== false;
    }

    /** @return bool true once the root exists, is writable, and carries its protective files. */
    private function ensureProtectedRoot(): bool
    {
        $root = $this->root();
        if ($root === '') {
            return false;
        }
        if (!is_dir($root) && !@mkdir($root, 0750, true)) {
            return false;
        }
        if (!is_writable($root)) {
            return false;
        }

        $htaccess = $root . '/.htaccess';
        if (!is_file($htaccess) && @file_put_contents($htaccess, "Require all denied\nDeny from all\n") === false) {
            return false;
        }
        $index = $root . '/index.php';
        if (!is_file($index) && @file_put_contents($index, "<?php\n// Silence is golden.\n") === false) {
            return false;
        }

        return true;
    }
}
