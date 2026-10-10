<?php

namespace QSD\Platform\Core;

use QSD\Platform\Modules\Account\AccountModule;
use QSD\Platform\Modules\Admin\AdminModule;
use QSD\Platform\Modules\AdminStation\AdminStationAuth;
use QSD\Platform\Modules\AdminStation\AdminStationModule;
use QSD\Platform\Modules\Service\ServiceModule;
use QSD\Platform\Modules\Settings\SettingsModule;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

/**
 * Platform boot. WordPress is the runtime and storage host only: this wires
 * access, entity registration, the permanent Platform ID station, the domain
 * Stations (Service, Category, Account), the Settings Station, and the Admin Station host.
 *
 * Adding a Station: construct its module here and inject the shared
 * PlatformIdentifierStation when the Station issues permanent identifiers.
 */
final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        (new PlatformAccess())->register();
        (new PostTypeRegistrar())->register();
        (new TaxonomyRegistrar())->register();
        (new MailService())->register();
        (new AssetLoader())->register();

        $platformIdentifiers = new PlatformIdentifierStation();

        (new ServiceModule($platformIdentifiers))->register();
        (new AccountModule($platformIdentifiers))->register();
        (new AdminModule($platformIdentifiers))->register();
        (new SettingsModule())->register();
        (new AdminStationModule())->register();
        (new AdminStationAuth())->register();

        add_action('rest_api_init', [self::class, 'registerCoreRoutes']);
    }

    public static function registerCoreRoutes(): void
    {
        register_rest_route('qsd/v1', '/health', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'healthCheck'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Public liveness probe. Anonymous callers get only the aggregate status;
     * the per-subsystem checks and the platform version are returned to
     * authenticated platform managers only.
     */
    public static function healthCheck(\WP_REST_Request $request): \WP_REST_Response
    {
        $checks     = Health::run();
        $allHealthy = empty($checks) || !in_array(false, $checks, true);

        $body = [
            'success' => $allHealthy,
            'status'  => $allHealthy ? 'healthy' : 'degraded',
        ];

        if (current_user_can(PlatformAccess::CAP)) {
            $body['version'] = defined('QSD_PLUGIN_VERSION') ? QSD_PLUGIN_VERSION : null;
            $body['checks']  = $checks;
        }

        return rest_ensure_response($body);
    }
}
