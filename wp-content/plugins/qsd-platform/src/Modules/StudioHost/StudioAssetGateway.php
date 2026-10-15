<?php

namespace QSD\Platform\Modules\StudioHost;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Account\Support\AccountMedia;
use QSD\Platform\Modules\Account\Support\AccountRepository;
use QSD\Platform\Modules\Account\Support\AccountSchema;

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
 *
 * Every manifest-supplied path is rejected outright if it is not a plain,
 * URL-safe relative form (see `isUrlSafeRelativePath()`) before any
 * filesystem check, and is further required to resolve, by real path, inside
 * `studio-assets/` (see `assetPath()`). The identical validated string is
 * used both to resolve the real file and to build the URL handed to the
 * browser, so there is never a gap between what was checked and what is
 * actually requested.
 */
class StudioAssetGateway
{
    public const DIR_NAME = 'studio-assets';

    private const SCRIPT_HANDLE = 'qsd-studio-host';
    private const STYLE_HANDLE  = 'qsd-studio-host';
    private const CONFIG_HANDLE = 'qsd-studio-config';

    private \Closure $resolveLogoUrl;

    /**
     * @param callable(): ?string|null $resolveLogoUrl Test seam only; production
     *     reads the real Account Brand projection via AccountRepository/AccountMedia.
     */
    public function __construct(?callable $resolveLogoUrl = null)
    {
        $this->resolveLogoUrl = $resolveLogoUrl === null
            ? static fn(): ?string => self::currentLogoUrlFromAccount()
            : \Closure::fromCallable($resolveLogoUrl);
    }

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
     * Null when the string fails the URL-safety check below, is missing, or
     * resolves (via `realpath()`) outside the asset root — defence in depth
     * only: every filename here comes from WEX's own manifest file, never
     * from a request. `realpath()` on `$relative` also resolves a symlink to
     * its real target, so a symlink planted inside `studio-assets/` that
     * points outside it is rejected by the same containment check as a
     * plain `../` traversal.
     *
     * The containment check requires an exact match or a boundary at a
     * directory separator — a bare `str_starts_with($real, $root)` would
     * wrongly accept a sibling directory whose name merely starts with the
     * same characters (e.g. a sibling `studio-assets-evil/` next to
     * `studio-assets/`), since the string "studio-assets" is itself a
     * prefix of "studio-assets-evil".
     *
     * `isUrlSafeRelativePath()` runs first, before any filesystem call: the
     * exact same `$relative` string that passes here is also what
     * `assetUrl()` sends to the browser, so rejecting an unsafe form here
     * guarantees it is never enqueued as a URL either — not merely blocked
     * from being read server-side while still reaching the client.
     */
    private function assetPath(string $relative): ?string
    {
        if (!$this->isUrlSafeRelativePath($relative)) {
            return null;
        }
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

    /**
     * A plain, forward-slash relative path only: no `..`/`.` segment, no
     * leading slash or backslash, no Windows drive letter, and no `%`
     * (rejected outright rather than decoded — a manifest value is never a
     * URL that should carry percent-encoding in the first place). This is
     * an explicit input-validation layer independent of the `realpath()`
     * containment check in `assetPath()`, not a replacement for it.
     */
    private function isUrlSafeRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '%')) {
            return false;
        }
        if (str_starts_with($relative, '/') || str_starts_with($relative, '\\') || str_contains($relative, '\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:/', $relative) === 1) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }

    private function assetUrl(string $relative): string
    {
        return QSD_PLUGIN_URL . self::DIR_NAME . '/' . $relative;
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
        $this->outputHostOptions();
    }

    /**
     * `globalThis.wexStudioHostOptions` — the one-time, read-only data handoff
     * WEX's accepted bundle reads at startup to bind the authorised Account
     * Brand logo into its own Logo component. Attached to the same
     * CONFIG_HANDLE inline script, so it is always written before the WEX
     * entry script (SCRIPT_HANDLE depends on CONFIG_HANDLE) ever runs.
     *
     * Carries only the resolved logo URL — never the Brand name/code, a
     * Platform ID, or any other Account/Profile field. Null when no logo is
     * published; WEX's own component owns the missing/invalid-image fallback.
     *
     * The resolved value is re-validated against `sanitizeLogoUrl()` right
     * here, at the point it crosses into WEX's trust boundary — not trusted
     * merely because it came from `AccountMedia::url()`. This is the one
     * seam QSD controls end-to-end: whatever shape the value has by the time
     * it reaches this line, WEX only ever receives exactly that clean HTTPS
     * form or `null`, never anything in between (e.g. a value wrapped in
     * Markdown link syntax, which is otherwise indistinguishable from a
     * valid URL to a bare `filter_var($url, FILTER_VALIDATE_URL)` check).
     */
    private function outputHostOptions(): void
    {
        $options = wp_json_encode([
            'logoImageBinding' => ['source' => 'profile.logo', 'target' => 'imageUrl'],
            'sourceValues'     => ['profile.logo' => self::sanitizeLogoUrl(($this->resolveLogoUrl)())],
        ]);

        wp_add_inline_script(self::CONFIG_HANDLE, 'globalThis.wexStudioHostOptions = ' . $options . ';');
    }

    /**
     * Anchored, whole-string match against the exact shape `AccountMedia::url()`
     * produces: `https://`, then a plain host, then a plain path ending in
     * `/qsd-account/<hex>.<allowed extension>`. Anything else — including a
     * value that is otherwise a valid URL but wrapped in surrounding
     * characters, such as Markdown link syntax (`[https://...](https://...)`)
     * — resolves to `null`, the same fallback WEX already renders for "no
     * logo published". Never widen this into a general sanitizer; it exists
     * only to guarantee this one handoff.
     */
    private static function sanitizeLogoUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $extensions = implode('|', array_map(
            static fn(string $ext): string => preg_quote($ext, '#'),
            AccountMedia::extensions()
        ));
        $pattern = '#^https://[A-Za-z0-9.-]+(?::\d+)?(?:/[A-Za-z0-9._-]+)*/'
            . preg_quote(AccountMedia::DIRECTORY, '#')
            . '/[0-9a-f]+\.(?:' . $extensions . ')$#';

        return preg_match($pattern, $url) === 1 ? $url : null;
    }

    /** The authorised Account Brand logo URL, read the same way Account's own projection computes it. */
    private static function currentLogoUrlFromAccount(): ?string
    {
        $state   = (new AccountRepository())->state();
        $brand   = is_array($state['brand'] ?? null) ? $state['brand'] : AccountSchema::emptyBrand();
        $mediaId = $brand['logo_media_id'] ?? null;

        return !empty($mediaId) ? (new AccountMedia())->url((string) $mediaId) : null;
    }
}
