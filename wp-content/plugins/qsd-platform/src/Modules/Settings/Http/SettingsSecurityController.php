<?php

namespace QSD\Platform\Modules\Settings\Http;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Settings\Security\BrokerValidation;
use QSD\Platform\Modules\Settings\Security\CredentialAuthority;

/**
 * SettingsSecurityController — the Security Phase 2 runtime validation route.
 *
 *   POST qsd/v1/admin/settings/security/broker-validation
 *
 * Runs BrokerValidation once on this install's real storage and returns its
 * safe report. Administrator-only (platform capability + CredentialAuthority),
 * because the run uses the stored provider credential for one read-only check.
 *
 * Identity is derived here, server-side: the WordPress user is the
 * authenticated session user and the caller is the fixed, allow-listed
 * `BrokerValidation::CALLER`. The request body is ignored entirely, so a client
 * cannot choose another user id, caller, provider, scope or subject.
 */
class SettingsSecurityController
{
    /** @param \Closure(): BrokerValidation $validation */
    public function __construct(private \Closure $validation) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('qsd/v1', '/admin/settings/security/broker-validation', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runValidation'],
            'permission_callback' => [$this, 'requireAuthority'],
        ]);
    }

    public function runValidation(\WP_REST_Request $request): \WP_REST_Response
    {
        $report = ($this->validation)()->run(get_current_user_id());
        return rest_ensure_response(['success' => $report['passed'], 'validation' => $report]);
    }

    public function requireAuthority(): bool
    {
        return current_user_can(PlatformAccess::CAP) && CredentialAuthority::allows();
    }
}
