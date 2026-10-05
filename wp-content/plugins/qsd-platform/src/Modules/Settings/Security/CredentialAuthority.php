<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * CredentialAuthority — who may change provider secrets.
 *
 * Creating, replacing, clearing or disconnecting a provider secret needs the
 * existing WordPress administrator capability `manage_options`, which is
 * stricter than the business platform capability `manage_qsd`. Seeing safe,
 * non-secret connection state and editing non-secret configuration stay with
 * `manage_qsd`. This adds no role or capability family.
 */
final class CredentialAuthority
{
    public const CAP = 'manage_options';

    public static function allows(): bool
    {
        return current_user_can(self::CAP);
    }
}
