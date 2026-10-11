<?php

namespace QSD\Platform\Modules\StudioHost;

use QSD\Platform\Core\Health;
use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\AdminStation\AdminStationModule;

/**
 * The QSD-owned host for the independently built WEX Admin Dashboard at the
 * fixed route /studio/, coexisting with /station/ without changing it.
 *
 * Like AdminStationModule, the route is served entirely by the plugin (no
 * WordPress page, shortcode, or theme template) and renders one of two
 * states inside its own, separate document template:
 *
 *   not signed in, or signed in without the platform capability
 *                           → a bare access-denied state linking to /station/
 *   PlatformAccess::CAP     → the Studio mount point
 *
 * WordPress remains the sole session/auth host — this module reuses the same
 * existing manage_qsd capability /station/ already gates on, rather than
 * inventing a second authorization scheme. No separate login form exists
 * here: a visitor who needs to sign in is pointed at the one real sign-in
 * page (AdminStationModule::url(), consumed only as a public URL, not as a
 * template/UI import) instead of a duplicate being built.
 *
 * This module owns only the route, the document, and the gate states. It
 * owns no WEX markup, presentation, or identity semantics, and no QSD
 * business data.
 */
class StudioHostModule
{
    public const ROUTE_SLUG = 'studio';
    public const QUERY_VAR  = 'qsd_studio';
    public const MOUNT_ID   = 'qsd-studio-host';

    /** Bump when the rewrite rule changes; triggers one flush on the next request. */
    private const REWRITE_VERSION        = '1';
    private const REWRITE_VERSION_OPTION = 'qsd_studio_rewrite_version';

    public function register(): void
    {
        add_action('init', [$this, 'registerRoute']);
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_filter('template_include', [$this, 'templateInclude'], 99);
        add_filter('wp_robots', [$this, 'robots']);
        add_filter('show_admin_bar', [$this, 'showAdminBar']);
        Health::register('studio-host', static fn() => true);
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

    /** True while WordPress is serving the Studio host route. Valid from parse_query on. */
    public static function isStudioRequest(): bool
    {
        return (string) get_query_var(self::QUERY_VAR) === '1';
    }

    /**
     * The canonical Studio URL. Falls back to the query-var form when the
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
        if (!self::isStudioRequest()) {
            return $template;
        }
        nocache_headers();
        status_header(200);
        return QSD_APP_PATH . 'modules/studio-host/templates/studio-document.php';
    }

    /** The Studio host is never indexed. */
    public function robots(array $robots): array
    {
        if (self::isStudioRequest()) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }

    /** The Studio host is a full-screen application; the WordPress toolbar never overlays it. */
    public function showAdminBar(bool $show): bool
    {
        return self::isStudioRequest() ? false : $show;
    }

    /**
     * The document body for the current visitor: access-denied, or the mount
     * point. Called by studio-document.php.
     */
    public static function renderBody(): string
    {
        if (!is_user_logged_in() || !current_user_can(PlatformAccess::CAP)) {
            return self::renderTemplate('access-denied.php', [
                'stationUrl' => AdminStationModule::url(),
            ]);
        }

        return self::renderTemplate('studio-mount.php');
    }

    /** @param array<string, mixed> $vars */
    private static function renderTemplate(string $file, array $vars = []): string
    {
        $template = QSD_APP_PATH . 'modules/studio-host/templates/' . $file;
        if (!file_exists($template)) {
            return '';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }
}
