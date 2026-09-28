<?php

namespace QSD\Platform\Modules\AdminStation;

use QSD\Platform\Core\Health;
use QSD\Platform\Core\PlatformAccess;

/**
 * The independent administration environment and sole admin frontend host.
 *
 * The Admin Station lives at a fixed route (/station/) served entirely by the
 * plugin: no WordPress page, shortcode, or theme template is involved, so
 * WordPress stays a runtime and storage host. The route renders one of three
 * states inside the plugin's own document template:
 *
 *   logged out             → the branded login gate (AdminStationAuth signs in)
 *   logged in, no access   → a product-styled access-denied state
 *   PlatformAccess::CAP    → the Admin Station mount point
 *
 * This module owns only the route, the document, and the gate states. It owns
 * no persistence or domain authority.
 */
class AdminStationModule
{
    public const ROUTE_SLUG = 'station';
    public const QUERY_VAR  = 'qsd_station';
    public const MOUNT_ID   = 'qsd-admin-station';

    /** Bump when the rewrite rule changes; triggers one flush on the next request. */
    private const REWRITE_VERSION        = '1';
    private const REWRITE_VERSION_OPTION = 'qsd_station_rewrite_version';

    public function register(): void
    {
        add_action('init', [$this, 'registerRoute']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_filter('template_include', [$this, 'templateInclude'], 99);
        add_filter('wp_robots', [$this, 'robots']);
        add_filter('show_admin_bar', [$this, 'showAdminBar']);
        Health::register('admin-station', static fn() => true);
    }

    // ── Route ─────────────────────────────────────────────────────────────────

    public function registerRoute(): void
    {
        add_rewrite_rule('^' . self::ROUTE_SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');

        // Deploys copy files rather than reactivating the plugin, so the rule is
        // flushed once per REWRITE_VERSION instead of only on activation.
        if (get_option(self::REWRITE_VERSION_OPTION) !== self::REWRITE_VERSION) {
            flush_rewrite_rules(false);
            update_option(self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false);
        }
    }

    /** @param string[] $vars */
    public function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /** True while WordPress is serving the Admin Station route. Valid from parse_query on. */
    public static function isStationRequest(): bool
    {
        return (string) get_query_var(self::QUERY_VAR) === '1';
    }

    /**
     * The canonical Admin Station URL. Falls back to the query-var form when the
     * site runs without pretty permalinks, so the route always resolves.
     */
    public static function url(): string
    {
        if ((string) get_option('permalink_structure') === '') {
            return add_query_arg(self::QUERY_VAR, '1', home_url('/'));
        }
        return home_url('/' . self::ROUTE_SLUG . '/');
    }

    // ── Document ──────────────────────────────────────────────────────────────

    public function templateInclude(string $template): string
    {
        if (!self::isStationRequest()) {
            return $template;
        }
        nocache_headers();
        status_header(200);
        return QSD_APP_PATH . 'modules/admin-station/templates/station-document.php';
    }

    /** The Admin Station is never indexed. */
    public function robots(array $robots): array
    {
        if (self::isStationRequest()) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }

    /** The Admin Station is a full-screen application; the WordPress toolbar never overlays it. */
    public function showAdminBar(bool $show): bool
    {
        return self::isStationRequest() ? false : $show;
    }

    /**
     * The document body for the current visitor: login gate, access denied, or
     * the mount point. Called by station-document.php.
     */
    public static function renderBody(): string
    {
        if (!is_user_logged_in()) {
            return self::renderTemplate('login-gate.php', [
                'hasError' => !empty($_GET['login_error']),
                'nonce'    => wp_create_nonce(AdminStationAuth::NONCE_ACTION),
            ]);
        }

        if (!current_user_can(PlatformAccess::CAP)) {
            return self::renderTemplate('access-denied.php');
        }

        return self::renderTemplate('admin-station.php');
    }

    /** @param array<string, mixed> $vars */
    private static function renderTemplate(string $file, array $vars = []): string
    {
        $template = QSD_APP_PATH . 'modules/admin-station/templates/' . $file;
        if (!file_exists($template)) {
            return '';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }
}
