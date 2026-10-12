<?php

namespace QSD\Platform\Modules\StudioHost;

use QSD\Platform\Core\PlatformAccess;

/**
 * Versioned static delivery for WEX-supplied bundles.
 *
 * `studio-assets/` is a public, directly fetchable plugin-local directory —
 * the same trust model the existing Admin Station bundle already relies on:
 * the bundle itself carries no secret and no business data. It is only ever
 * enqueued on the authorized /studio/ route, for a signed-in platform
 * manager, and it imports no QSD visual code and depends on no GitHub Pages
 * path. Until WEX supplies `studio-assets/manifest.json` and its build, the
 * host route still renders its own mount point with nothing enqueued — the
 * host does not depend on WEX's bundle existing yet.
 */
class StudioAssetGateway
{
    public const DIR_NAME = 'studio-assets';

    private const SCRIPT_HANDLE = 'qsd-studio-host';
    private const STYLE_HANDLE  = 'qsd-studio-host';
    private const CONFIG_HANDLE = 'qsd-studio-config';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('script_loader_tag', [$this, 'setModuleType'], 10, 2);
    }

    public function enqueue(): void
    {
        if (!StudioHostModule::isStudioRequest()) {
            return;
        }
        if (!is_user_logged_in() || !current_user_can(PlatformAccess::CAP)) {
            return;
        }

        $manifest = $this->readManifest();
        if ($manifest === null) {
            return;
        }

        $this->outputRuntimeConfig();

        $styles = is_array($manifest['styles'] ?? null) ? $manifest['styles'] : [];
        foreach ($styles as $i => $file) {
            $path = $this->assetPath((string) $file);
            if ($path !== null) {
                wp_enqueue_style(self::STYLE_HANDLE . '-' . $i, $this->assetUrl((string) $file), [], (string) filemtime($path));
            }
        }

        $entry = isset($manifest['entry']) ? (string) $manifest['entry'] : '';
        $path  = $entry !== '' ? $this->assetPath($entry) : null;
        if ($path !== null) {
            wp_enqueue_script(self::SCRIPT_HANDLE, $this->assetUrl($entry), [self::CONFIG_HANDLE], (string) filemtime($path), true);
        }
    }

    public function setModuleType(string $tag, string $handle): string
    {
        if ($handle === self::SCRIPT_HANDLE) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    /** @return array{entry?: string, styles?: string[], version?: string}|null */
    private function readManifest(): ?array
    {
        $path = $this->assetPath('manifest.json');
        if ($path === null) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Null when missing, or when the resolved real path escapes the asset
     * root — defence in depth only: every filename here comes from WEX's own
     * manifest file, never from a request. `realpath()` on `$relative` also
     * resolves a symlink to its real target, so a symlink planted inside
     * `studio-assets/` that points outside it is rejected by the same
     * containment check as a plain `../` traversal.
     *
     * The containment check requires an exact match or a boundary at a
     * directory separator — a bare `str_starts_with($real, $root)` would
     * wrongly accept a sibling directory whose name merely starts with the
     * same characters (e.g. a sibling `studio-assets-evil/` next to
     * `studio-assets/`), since the string "studio-assets" is itself a
     * prefix of "studio-assets-evil".
     */
    private function assetPath(string $relative): ?string
    {
        $root = realpath($this->rootPath());
        if ($root === false) {
            return null;
        }
        $real = realpath($root . '/' . $relative);
        if ($real === false) {
            return null;
        }
        if ($real !== $root && !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($real) ? $real : null;
    }

    private function assetUrl(string $relative): string
    {
        return QSD_PLUGIN_URL . self::DIR_NAME . '/' . ltrim($relative, '/');
    }

    private function rootPath(): string
    {
        return rtrim(QSD_PLUGIN_PATH, '/') . '/' . self::DIR_NAME;
    }

    /** Scoped to only the Studio routes — never the full qsd/v1 business API root. */
    private function outputRuntimeConfig(): void
    {
        wp_register_script(self::CONFIG_HANDLE, false, [], null, true);
        wp_enqueue_script(self::CONFIG_HANDLE);

        $config = wp_json_encode([
            'apiRoot' => esc_url_raw(rest_url('qsd/v1/studio/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_add_inline_script(self::CONFIG_HANDLE, 'window.QSDStudioConfig = ' . $config . ';');
    }
}
