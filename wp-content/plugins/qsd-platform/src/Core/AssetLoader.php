<?php

namespace QSD\Platform\Core;

use QSD\Platform\Modules\AdminStation\AdminStationModule;

/**
 * Loads the Admin Station's assets — on the /station/ route only.
 *
 * WordPress serves no other platform UI, so nothing is enqueued on any other
 * request: the public website is a separate front end that talks to the
 * qsd/v1 API.
 *
 * Load order on the station route:
 *   Atomic Engine tokens/base (00–09) → drawer-kit.css → admin-station.css
 *   window.QSDConfig → admin-station.js (ES module)
 */
class AssetLoader
{
    private const SCRIPT_HANDLE = 'qsd-admin-station';
    private const STYLE_HANDLE  = 'qsd-admin-station';
    private const DRAWER_HANDLE = 'qsd-drawer-kit';
    private const CONFIG_HANDLE = 'qsd-config';

    private const ATOMIC_FILES = [
        '00-tokens.css', '01-reset.css', '02-base.css', '03-layout.css',
        '04-buttons.css', '05-cards.css', '06-forms.css', '07-tabs.css',
        '08-modals.css', '09-utilities.css',
    ];

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('script_loader_tag', [$this, 'setModuleType'], 10, 2);
    }

    public function enqueue(): void
    {
        if (!AdminStationModule::isStationRequest()) {
            return;
        }

        $atomicHandle = $this->enqueueAtomicStyles();
        $this->enqueueStationStyles($atomicHandle);

        // The config and the app bundle are only for a signed-in platform
        // manager; the login gate and access-denied states need styles only.
        if (is_user_logged_in() && current_user_can(PlatformAccess::CAP)) {
            $this->outputRuntimeConfig();
            $this->enqueueStationScript();
        }
    }

    public function setModuleType(string $tag, string $handle): string
    {
        if ($handle === self::SCRIPT_HANDLE) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    /** @return string the handle of the last Atomic stylesheet, for dependency ordering */
    private function enqueueAtomicStyles(): string
    {
        $handle = '';
        foreach (self::ATOMIC_FILES as $i => $file) {
            $handle = 'qsd-atomic-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            wp_enqueue_style($handle, QSD_ATOMIC_ENGINE_URL . 'css/' . $file, [], QSD_PLUGIN_VERSION);
        }
        return $handle;
    }

    /**
     * The shared entity-drawer stylesheet loads before the Admin Station
     * stylesheet (as its dependency), so the station's own chrome keeps the
     * last word on any overlap.
     */
    private function enqueueStationStyles(string $after): void
    {
        $drawer  = 'css/drawer-kit.css';
        $station = 'css/admin-station.css';

        if (file_exists(QSD_DIST_PATH . $drawer)) {
            wp_enqueue_style(self::DRAWER_HANDLE, QSD_DIST_URL . $drawer, [$after], (string) filemtime(QSD_DIST_PATH . $drawer));
        }
        if (file_exists(QSD_DIST_PATH . $station)) {
            wp_enqueue_style(self::STYLE_HANDLE, QSD_DIST_URL . $station, [self::DRAWER_HANDLE], (string) filemtime(QSD_DIST_PATH . $station));
        }
    }

    private function enqueueStationScript(): void
    {
        $script = 'js/admin-station.js';
        if (file_exists(QSD_DIST_PATH . $script)) {
            wp_enqueue_script(self::SCRIPT_HANDLE, QSD_DIST_URL . $script, [self::CONFIG_HANDLE], (string) filemtime(QSD_DIST_PATH . $script), true);
        }
    }

    /** window.QSDConfig — API root, REST nonce, and the Admin Station logout URL. */
    private function outputRuntimeConfig(): void
    {
        wp_register_script(self::CONFIG_HANDLE, false, [], null, true);
        wp_enqueue_script(self::CONFIG_HANDLE);

        $config = wp_json_encode([
            'apiRoot'   => esc_url_raw(rest_url('qsd/v1/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'logoutUrl' => $this->logoutUrl(),
        ]);

        wp_add_inline_script(self::CONFIG_HANDLE, 'window.QSDConfig = ' . $config . ';');
    }

    /**
     * The nonce-protected WordPress logout URL, returning to the Admin Station
     * (which then shows its login gate), decoded for JSON/JS use.
     *
     * wp_logout_url() delegates to wp_nonce_url(), which HTML-encodes the URL
     * (`&` becomes `&amp;`). That is right for markup, but this value is
     * assigned as a DOM href from JavaScript where nothing decodes it, so the
     * literal `&amp;` would rename every parameter after the first and drop
     * the nonce. wp_specialchars_decode(..., ENT_QUOTES) is the exact inverse.
     */
    private function logoutUrl(): string
    {
        return esc_url_raw(wp_specialchars_decode(wp_logout_url(AdminStationModule::url()), ENT_QUOTES));
    }
}
