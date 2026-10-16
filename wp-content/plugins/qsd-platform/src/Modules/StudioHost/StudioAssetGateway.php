<?php

namespace QSD\Platform\Modules\StudioHost;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Account\Support\AccountMedia;
use QSD\Platform\Modules\Account\Support\AccountRepository;
use QSD\Platform\Modules\Account\Support\AccountSchema;

/**
 * Versioned delivery for the WEX-supplied Studio bundle.
 *
 * The bundle is never copied into this repository. `StudioReleaseLock`
 * fetches and verifies exactly one QSD-approved, immutable WEX release over
 * HTTPS; this class enqueues only what that lock already confirmed, with a
 * Subresource Integrity digest on every `<script>`/`<link>` tag it adds. It
 * is only ever enqueued on the authorized /studio/ route, for a signed-in
 * platform manager. Until the lock can verify a release, the host route
 * still renders its own mount point with nothing enqueued — the host does
 * not depend on WEX's bundle being reachable.
 */
class StudioAssetGateway
{
    private const SCRIPT_HANDLE = 'qsd-studio-host';
    private const STYLE_HANDLE  = 'qsd-studio-host';
    private const CONFIG_HANDLE = 'qsd-studio-config';

    private \Closure $resolveLogoUrl;
    private StudioReleaseLock $releaseLock;

    /** @var array<string, string> Handle => SRI digest, populated by enqueue() for the output filters below. */
    private array $integrityByHandle = [];

    /**
     * @param callable(): ?string|null $resolveLogoUrl Test seam only; production
     *     reads the real Account Brand projection via AccountRepository/AccountMedia.
     * @param StudioReleaseLock|null $releaseLock Test seam only; production uses the real QSD-approved pin.
     */
    public function __construct(?callable $resolveLogoUrl = null, ?StudioReleaseLock $releaseLock = null)
    {
        $this->resolveLogoUrl = $resolveLogoUrl === null
            ? static fn(): ?string => self::currentLogoUrlFromAccount()
            : \Closure::fromCallable($resolveLogoUrl);
        $this->releaseLock = $releaseLock ?? new StudioReleaseLock();
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('script_loader_tag', [$this, 'setScriptAttributes'], 10, 2);
        add_filter('style_loader_tag', [$this, 'setStyleAttributes'], 10, 2);
    }

    public function enqueue(): void
    {
        if (!StudioHostModule::isStudioRequest()) {
            return;
        }
        if (!is_user_logged_in() || !current_user_can(PlatformAccess::CAP)) {
            return;
        }

        $release = $this->releaseLock->verifiedRelease();
        if ($release === null) {
            return;
        }

        $this->outputRuntimeConfig();

        foreach ($release['styles'] as $i => $file) {
            $handle = self::STYLE_HANDLE . '-' . $i;
            wp_enqueue_style($handle, $this->releaseLock->assetUrl($file), [], $release['releaseId']);
            $this->integrityByHandle[$handle] = $release['files'][$file];
        }

        $entry = $release['entry'];
        wp_enqueue_script(self::SCRIPT_HANDLE, $this->releaseLock->assetUrl($entry), [self::CONFIG_HANDLE], $release['releaseId'], true);
        $this->integrityByHandle[self::SCRIPT_HANDLE] = $release['files'][$entry];
    }

    public function setScriptAttributes(string $tag, string $handle): string
    {
        if ($handle === self::SCRIPT_HANDLE) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
            $tag = $this->withIntegrity($tag, $handle);
        }
        return $tag;
    }

    public function setStyleAttributes(string $tag, string $handle): string
    {
        return $this->withIntegrity($tag, $handle);
    }

    /**
     * Adds `integrity`/`crossorigin` to the one tag this release's digest
     * inventory already verified — never to a handle this gateway did not
     * itself enqueue from `$release['files']`, and never a value not
     * already confirmed by `StudioReleaseLock`.
     */
    private function withIntegrity(string $tag, string $handle): string
    {
        $digest = $this->integrityByHandle[$handle] ?? null;
        if ($digest === null) {
            return $tag;
        }
        $attrs = ' integrity="' . esc_attr($digest) . '" crossorigin="anonymous"';
        return preg_replace('/^(<(?:script|link)\b)/', '$1' . $attrs, $tag, 1) ?? $tag;
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
