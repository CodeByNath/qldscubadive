<?php

namespace QSD\Platform\Modules\Admin\Http;

use QSD\Platform\Core\Health;

class AdminController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('qsd/v1', '/admin/overview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getOverview'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

    }

    public function getOverview(\WP_REST_Request $request): \WP_REST_Response
    {
        $counts = wp_count_posts('qsd_service');

        return rest_ensure_response([
            'services_published' => (int) ($counts->publish ?? 0),
            'services_draft'     => (int) ($counts->draft ?? 0),
            'health'             => Health::run(),
            'platform_version'   => defined('QSD_PLUGIN_VERSION') ? QSD_PLUGIN_VERSION : null,
        ]);
    }

    public function requireAdmin(): bool
    {
        return current_user_can(\QSD\Platform\Core\PlatformAccess::CAP);
    }
}
