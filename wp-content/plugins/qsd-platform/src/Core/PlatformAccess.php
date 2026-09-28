<?php

namespace QSD\Platform\Core;

/**
 * PlatformAccess — the shared platform capability and role.
 *
 * This is the single backend authority for who may reach QSD's
 * authenticated admin surfaces. It owns only access registration: the platform
 * capability, the role that carries it, and the transparent grant for
 * developers. It never creates accounts, and contains no routing, menu,
 * redirect, asset, or UI logic.
 *
 * Capability model:
 *   manage_qsd — the platform capability that gates the admin surfaces.
 *   Granted natively to users in the qsd_platform_manager role (registered here).
 *   Also granted transparently (via user_has_cap filter) to any user who has
 *   manage_options, so developer accounts retain access without role migration.
 *
 * Provisioning:
 *   Create business users in WordPress and assign the 'qsd_platform_manager'
 *   role. They receive manage_qsd natively and never need install_plugins.
 */
class PlatformAccess
{
    public const CAP  = 'manage_qsd';
    public const ROLE = 'qsd_platform_manager';

    public function register(): void
    {
        add_action('init',         [$this, 'registerRole'],         1);
        add_filter('user_has_cap', [$this, 'grantPlatformCap'], 10, 4);
    }

    // ── Role ──────────────────────────────────────────────────────────────────

    /**
     * Register the platform manager role on init if it does not yet exist.
     * The role carries manage_qsd and read only — no WP admin surface access.
     * Also repairs a stale DB entry where the role exists but is missing manage_qsd
     * (e.g., from a previous deploy that stored an incomplete capability set).
     * Idempotent: safe to run on every request.
     */
    public function registerRole(): void
    {
        $role = get_role(self::ROLE);

        if ($role === null) {
            add_role(self::ROLE, 'Platform Manager', [
                self::CAP => true,
                'read'    => true,
            ]);
            return;
        }

        if (empty($role->capabilities[self::CAP])) {
            $role->add_cap(self::CAP, true);
        }
    }

    // ── Capability ────────────────────────────────────────────────────────────

    /**
     * Grant manage_qsd to any user who already has manage_options.
     * Fires on every current_user_can() call — keep the fast path cheap.
     */
    public function grantPlatformCap(array $allCaps, array $caps, array $args, \WP_User $user): array
    {
        if (!empty($allCaps[self::CAP]) || empty($allCaps['manage_options'])) {
            return $allCaps;
        }
        $allCaps[self::CAP] = true;
        return $allCaps;
    }
}
