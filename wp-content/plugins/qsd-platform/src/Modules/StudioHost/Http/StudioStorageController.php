<?php

namespace QSD\Platform\Modules\StudioHost\Http;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\StudioHost\StudioStorage;

/**
 * The minimum authorized host handshake WEX's approved bootstrap needs:
 * storage initialization status/action, and readback of its one
 * identity-space blob. No business-data API and no generic path-addressable
 * adapter is exposed — see Support\StudioStorage for why.
 */
class StudioStorageController
{
    public function __construct(private StudioStorage $storage) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('qsd/v1', '/studio/storage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'status'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/studio/storage/init', [
            'methods'             => 'POST',
            'callback'            => [$this, 'init'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/studio/storage/identity', [
            'methods'             => ['GET', 'PUT'],
            'callback'            => [$this, 'identity'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);
    }

    public function status(): \WP_REST_Response
    {
        return rest_ensure_response(['initialized' => $this->storage->isInitialized()]);
    }

    /** Administrator-approved first initialization: a runtime action triggered only by this explicit POST, never run automatically. */
    public function init(): \WP_REST_Response
    {
        $wasInitialized = $this->storage->isInitialized();
        $ok             = $this->storage->initialize(get_current_user_id());

        if (!$ok) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Studio storage could not be made ready.'], 500);
        }

        return rest_ensure_response(['initialized' => true, 'alreadyInitialized' => $wasInitialized]);
    }

    public function identity(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($request->get_method() === 'GET') {
            $raw = $this->storage->readIdentity();
            return rest_ensure_response(['identity' => $raw === null ? null : json_decode($raw, true)]);
        }

        if (!$this->storage->writeIdentity((string) $request->get_body())) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Storage not initialized, payload invalid, or too large.'], 409);
        }

        return rest_ensure_response(['success' => true]);
    }

    public function requireAdmin(): bool
    {
        return current_user_can(PlatformAccess::CAP);
    }
}
