<?php

namespace QSD\Platform\Modules\Account\Support;

use QSD\Platform\Modules\Admin\Support\StationLifecycle;

/**
 * AccountBrand — field sanitization and the draft/settle/publish/disable/
 * enable lifecycle rules for the Account singleton's one module.
 *
 * Brand has no required field, so a saved draft always settles cleanly —
 * there is no "incomplete, falls back to not-configured" case. Publish is
 * settle-then-activate as one step, and its status write is computed by the
 * shared `StationLifecycle` engine itself (`statusRouteTransition`), the same
 * call Service's own `/status` route makes — Account is restricted to the
 * active/disabled subset of that engine's vocabulary (it never mints
 * draft/archived/trashed records or wires Archive/Trash/Restore/Delete; see
 * this module's CLAUDE.md), not a second implementation of the transition.
 * Disable/Enable are a platform-visible presentation mask, never a lifecycle
 * rewrite — this mirrors Service's updateDisabledMask exactly, including
 * reusing `StationLifecycle::isLive()` for the same guard: Enable always
 * lands back on the unmasked 'disabled' status (Pending), never straight to
 * 'active'; the admin must Publish again to go live.
 */
final class AccountBrand
{
    public static function sanitizeName(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';

        return mb_substr($name, 0, AccountSchema::BRAND_NAME_MAX_LENGTH);
    }

    public static function sanitizeCode(mixed $value): string
    {
        $code = is_string($value) ? strtoupper(trim($value)) : '';
        $code = preg_replace('/[^A-Z]/', '', $code) ?? '';

        return substr($code, 0, AccountSchema::BRAND_CODE_MAX_LENGTH);
    }

    /**
     * Merge an input payload onto the current draft (or canonical brand, if
     * no draft exists yet) and mark the module pending.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function applyDraft(array $state, array $input): array
    {
        $base = $state['brand_draft'] ?? $state['brand'] ?? AccountSchema::emptyBrand();

        $state['brand_draft'] = [
            'name'             => array_key_exists('name', $input) ? self::sanitizeName($input['name']) : $base['name'],
            'code'             => array_key_exists('code', $input) ? self::sanitizeCode($input['code']) : $base['code'],
            'logo_media_id'    => array_key_exists('logo_media_id', $input) ? self::sanitizeMediaId($input['logo_media_id']) : $base['logo_media_id'],
            'favicon_media_id' => array_key_exists('favicon_media_id', $input) ? self::sanitizeMediaId($input['favicon_media_id']) : $base['favicon_media_id'],
        ];
        $state['module_status'][AccountSchema::MODULE_BRAND] = AccountSchema::MODULE_PENDING;

        return $state;
    }

    /** Promote the draft (or current canonical brand, if none) to canonical. Always settles. */
    public static function settle(array $state): array
    {
        $state['brand']                                       = $state['brand_draft'] ?? $state['brand'] ?? AccountSchema::emptyBrand();
        $state['brand_draft']                                 = null;
        $state['module_status'][AccountSchema::MODULE_BRAND] = AccountSchema::MODULE_SETTLED;

        return $state;
    }

    /**
     * Publish: settle whatever is pending, then activate via the shared
     * engine's `/status` transition (`StationLifecycle::statusRouteTransition`
     * targeting 'active') — the same call Service's canonical `/status` route
     * makes. Returns null only if `$state['platform_status']` is ever
     * something other than active/disabled, which Account never writes.
     *
     * @return array<string, mixed>|null
     */
    public static function publish(array $state): ?array
    {
        $state   = self::settle($state);
        $current = (string) ($state['platform_status'] ?? AccountSchema::STATUS_DISABLED);
        $change  = StationLifecycle::statusRouteTransition(
            $current,
            StationLifecycle::STATUS_ACTIVE,
            (string) ($state['previous_platform_status'] ?? '') !== '' ? (string) $state['previous_platform_status'] : null
        );
        if ($change === null) {
            return null;
        }

        $state['platform_status'] = $change['status'];

        return $state;
    }

    /**
     * Disable: explicit mask. Legal only from a live status (active or
     * disabled) — guarded by the same `StationLifecycle::isLive()` check
     * Service's `updateDisabledMask` uses. Captures previous_platform_status
     * once; an already-masked state stays masked with its original captured
     * value.
     *
     * @return array<string, mixed>|null null when illegal from the current status
     */
    public static function disable(array $state): ?array
    {
        $current = (string) ($state['platform_status'] ?? '');
        if (!StationLifecycle::isLive($current)) {
            return null;
        }

        if ($current === AccountSchema::STATUS_ACTIVE || (string) ($state['previous_platform_status'] ?? '') === '') {
            $state['previous_platform_status'] = $current;
        }
        $state['platform_status'] = AccountSchema::STATUS_DISABLED;

        return $state;
    }

    /**
     * Enable: clears the mask only. Never republishes — always lands back on
     * the unmasked 'disabled' status; the admin must Publish again.
     *
     * @return array<string, mixed>|null null when the current status is not disabled
     */
    public static function enable(array $state): ?array
    {
        if ((string) ($state['platform_status'] ?? '') !== AccountSchema::STATUS_DISABLED) {
            return null;
        }

        $state['platform_status']          = AccountSchema::STATUS_DISABLED;
        $state['previous_platform_status'] = '';

        return $state;
    }

    private static function sanitizeMediaId(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
