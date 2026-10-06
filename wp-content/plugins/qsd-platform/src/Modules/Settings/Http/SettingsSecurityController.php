<?php

namespace QSD\Platform\Modules\Settings\Http;

use QSD\Platform\Core\PlatformAccess;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\BrokerValidation;
use QSD\Platform\Modules\Settings\Security\CredentialAuthority;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;

/**
 * SettingsSecurityController — the Security operations behind API Keys.
 *
 *   POST qsd/v1/admin/settings/security/broker-validation  Test connection
 *   POST qsd/v1/admin/settings/security/rotation           Rotate encryption key
 *
 * Both are administrator-only (platform capability + CredentialAuthority):
 * validation uses the stored provider credential for one read-only check, and
 * rotation re-seals every stored credential under a new QSD data key.
 *
 * Identity is derived here, server-side: the WordPress user is the
 * authenticated session user and the caller is fixed server code. Request
 * bodies are ignored entirely, so a client cannot choose a user, caller,
 * provider, scope, subject or key. Neither response carries key material.
 */
class SettingsSecurityController
{
    public const ROTATION_CALLER = 'settings.security-rotation';

    /**
     * @param \Closure(): BrokerValidation   $validation
     * @param \Closure(): CredentialRotation $rotation
     */
    public function __construct(
        private \Closure $validation,
        private \Closure $rotation,
        private BrokerAuditLog $audit,
    ) {}

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
        register_rest_route('qsd/v1', '/admin/settings/security/rotation', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rotate'],
            'permission_callback' => [$this, 'requireAuthority'],
        ]);
    }

    public function runValidation(\WP_REST_Request $request): \WP_REST_Response
    {
        $report = ($this->validation)()->run(get_current_user_id());
        return rest_ensure_response(['success' => $report['passed'], 'validation' => $report]);
    }

    public function rotate(\WP_REST_Request $request): \WP_REST_Response
    {
        $report = ($this->rotation)()->rotate();
        if ($report['ok']) {
            $this->audit->record(BrokerAuditLog::ROTATED, ['caller' => self::ROTATION_CALLER, 'user_id' => get_current_user_id()], time());
        }
        $body = [
            'success'  => $report['ok'],
            'rotation' => ['resealed' => count($report['resealed']), 'unreadable' => $report['unreadable']],
        ];
        if (!$report['ok']) {
            $body['message'] = $report['error'];
        }
        return new \WP_REST_Response($body, $report['ok'] ? 200 : 409);
    }

    public function requireAuthority(): bool
    {
        return current_user_can(PlatformAccess::CAP) && CredentialAuthority::allows();
    }
}
