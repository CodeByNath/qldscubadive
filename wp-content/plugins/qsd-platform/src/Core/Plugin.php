<?php

namespace QSD\Platform\Core;

use QSD\Platform\Modules\Admin\AdminModule;
use QSD\Platform\Modules\AdminStation\AdminStationAuth;
use QSD\Platform\Modules\AdminStation\AdminStationModule;
use QSD\Platform\Modules\CostBuilder\CostBuilderModule;
use QSD\Platform\Modules\Homepage\HomepageModule;
use QSD\Platform\Modules\Promotions\PromotionsModule;
use QSD\Platform\Modules\Requests\RequestsModule;
use QSD\Platform\Modules\Service\ServiceModule;
use QSD\Platform\Modules\SurfacePackages\SurfacePackagesModule;
use QSD\Platform\Core\Health;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;
use QSD\Platform\PlatformIdentifier\ExistingRecordAssignmentCommand;
use QSD\Platform\PlatformIdentifier\TemporaryMigrationController;

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
        (new TemporaryMigrationController($platformIdentifiers))->register();
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command(
                'qsd platform-identifiers assign',
                new ExistingRecordAssignmentCommand($platformIdentifiers)
            );
        }
        (new SurfacePackagesModule($platformIdentifiers))->register();
        (new PromotionsModule())->register();
        (new CostBuilderModule())->register();
        (new HomepageModule())->register();
        (new RequestsModule($platformIdentifiers))->register();
        (new ServiceModule($platformIdentifiers))->register();
        (new AdminModule($platformIdentifiers))->register();
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

    public static function healthCheck(\WP_REST_Request $request): \WP_REST_Response
    {
        $checks     = Health::run();
        $allHealthy = empty($checks) || !in_array(false, $checks, true);

        return rest_ensure_response([
            'success' => $allHealthy,
            'status'  => $allHealthy ? 'healthy' : 'degraded',
            'version' => defined('QSD_PLUGIN_VERSION') ? QSD_PLUGIN_VERSION : null,
            'checks'  => $checks,
        ]);
    }
}
