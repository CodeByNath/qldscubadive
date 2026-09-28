<?php

namespace QSD\Platform\Modules\AdminStation;

/**
 * Processes the branded Admin Station login form's POST submission.
 *
 * WordPress remains the auth/session host: this class does nothing but
 * validate the request, hand credentials to wp_signon(), and redirect back
 * to the Admin Station. It owns no role, capability, or account provisioning
 * — that stays with Core\PlatformAccess.
 *
 * Processing is gated on the request actually being the Admin Station route
 * (AdminStationModule::isStationRequest(), a query var set only by the
 * plugin's own rewrite rule), and the post-login destination is always the
 * server-derived AdminStationModule::url() — never a client-supplied value,
 * never wp-login.php, never wp-admin.
 */
class AdminStationAuth
{
    public const NONCE_ACTION = 'qsd_admin_station_login';
    public const NONCE_FIELD  = 'qsd_admin_station_login_nonce';

    public function register(): void
    {
        // template_redirect fires early enough that wp_signon()'s auth
        // cookies can still be set before any HTML output starts.
        add_action('template_redirect', [$this, 'processLogin']);
    }

    public function processLogin(): void
    {
        $redirect = $this->handleLoginRequest(
            $_SERVER['REQUEST_METHOD'] ?? '',
            $_POST,
            AdminStationModule::isStationRequest(),
            AdminStationModule::url(),
        );
        if ($redirect === null) {
            return;
        }
        // Deliberately NOT wp_safe_redirect(): its own un-overridable
        // fallback is admin_url(). Validate explicitly against a
        // same-site, non-admin fallback instead, so no path here can
        // ever land on /wp-admin/.
        wp_redirect(wp_validate_redirect($redirect, home_url('/')));
        exit;
    }

    /**
     * Pure(ish) decision core, kept separate from processLogin()'s exit and
     * from every WordPress global-state read so it is directly testable.
     *
     * Returns the URL to redirect to, or null when this request is not a
     * submission of this form on the Admin Station page itself — wrong
     * page, wrong method, no nonce field, or a stale/invalid nonce are all
     * treated identically: no error, no redirect, the page just renders
     * normally (still showing the login form to a logged-out visitor).
     *
     * @param array<string, mixed> $post
     */
    public function handleLoginRequest(string $method, array $post, bool $isStationRequest, string $stationUrl): ?string
    {
        if (!$isStationRequest) {
            return null;
        }
        if ($method !== 'POST' || empty($post[self::NONCE_FIELD])) {
            return null;
        }
        if (!wp_verify_nonce(sanitize_key((string) $post[self::NONCE_FIELD]), self::NONCE_ACTION)) {
            return null;
        }

        // The only destination this ever returns to: the Admin Station's own
        // server-derived URL, with any stale prior-failure flag stripped so a
        // retry never stacks/echoes it once it succeeds. Never client input.
        $redirectTo = remove_query_arg('login_error', $stationUrl);

        $user = wp_signon([
            'user_login'    => sanitize_user(wp_unslash((string) ($post['qsd_username'] ?? ''))),
            'user_password' => wp_unslash((string) ($post['qsd_password'] ?? '')),
            'remember'      => false,
        ], is_ssl());

        if (is_wp_error($user)) {
            // Generic failure signal only — never the WP_Error's own message,
            // which distinguishes unknown-username from wrong-password.
            return add_query_arg('login_error', '1', $redirectTo);
        }

        return $redirectTo;
    }
}
