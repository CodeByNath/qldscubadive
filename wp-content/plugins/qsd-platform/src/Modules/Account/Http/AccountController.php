<?php

/*
 * FILE INDEX
 *
 * ACCOUNT_ROUTES   The qsd_account_station route registrations
 * HANDLERS         Read-only detail; bootstrap (identity only — no Brand yet)
 * PROJECTION       Wire shape shared by both handlers
 * AUTHORIZATION    Permission callback
 *
 * OWNERSHIP
 * Phase A only: the Account singleton hierarchy's permanent identity
 * (Account → Settings → Tools → Profile). No Brand field, draft, settle, or
 * Publish/Disable/Enable lifecycle exists yet — that is Phase B. fetchDetail
 * never mints; bootstrap is the only identity-minting entry point.
 */

namespace QSD\Platform\Modules\Account\Http;

use QSD\Platform\Modules\Account\Support\AccountIdentity;
use QSD\Platform\Modules\Account\Support\AccountRepository;
use QSD\Platform\Modules\Account\Support\AccountStorageBusy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierConflict;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierPolicy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

class AccountController
{
    private AccountIdentity $identity;

    public function __construct(private PlatformIdentifierStation $platformIdentifiers)
    {
        $this->identity = new AccountIdentity($this->platformIdentifiers, new AccountRepository());
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // ===================================================================
        // SECTION: ACCOUNT_ROUTES
        // ===================================================================
        register_rest_route('qsd/v1', '/admin/account', [
            'methods'             => 'GET',
            'callback'            => [$this, 'fetchDetail'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);

        register_rest_route('qsd/v1', '/admin/account/bootstrap', [
            'methods'             => 'POST',
            'callback'            => [$this, 'bootstrap'],
            'permission_callback' => [$this, 'requireAdmin'],
        ]);
    }

    // ===================================================================
    // SECTION: HANDLERS
    // ===================================================================

    /** Read-only. Never reserves or binds an identifier. */
    public function fetchDetail(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'account' => $this->projection($this->identity->nodes()),
        ]);
    }

    /**
     * Idempotent. Safe to call repeatedly or concurrently: a node already
     * bound is re-affirmed, not re-minted; a losing concurrent caller sees
     * PlatformIdentifierConflict and should retry.
     */
    public function bootstrap(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $nodes = $this->identity->bootstrap();
        } catch (AccountStorageBusy $error) {
            return new \WP_REST_Response(['success' => false, 'message' => $error->getMessage()], 503);
        } catch (PlatformIdentifierConflict) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Could not establish the Account Station identity. Please retry.',
            ], 500);
        }

        return rest_ensure_response([
            'success' => true,
            'account' => $this->projection($nodes),
        ]);
    }

    // ===================================================================
    // SECTION: PROJECTION
    // ===================================================================

    /** @param array<string, string|null> $nodes */
    private function projection(array $nodes): array
    {
        $bootstrapped = !in_array(null, $nodes, true);

        return [
            'bootstrapped' => $bootstrapped,
            'nodes'        => [
                'account'  => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT] ?? null],
                'settings' => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_SETTINGS] ?? null],
                'tools'    => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_TOOLS] ?? null],
                'profile'  => ['platform_id' => $nodes[PlatformIdentifierPolicy::ACCOUNT_PROFILE] ?? null],
            ],
        ];
    }

    // ===================================================================
    // SECTION: AUTHORIZATION
    // ===================================================================
    public function requireAdmin(): bool
    {
        return current_user_can(\QSD\Platform\Core\PlatformAccess::CAP);
    }
}
