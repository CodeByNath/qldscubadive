<?php

namespace QSD\Platform\Modules\Account\Support;

use QSD\Platform\PlatformIdentifier\PlatformIdentifierPolicy;

/**
 * AccountSchema — storage key, singleton node order, and native-reference
 * addresses for the Account identity hierarchy (Account → Settings → Tools →
 * Profile). Every level is a fixed singleton: exactly one record per site, so
 * each native reference is a constant string address, never a numeric id.
 */
final class AccountSchema
{
    public const OPTION_KEY = 'qsd_account_station_v1';

    public const MODULE_BRAND = 'brand';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';

    public const MODULE_NOT_CONFIGURED = 'not-configured';
    public const MODULE_PENDING        = 'pending';
    public const MODULE_SETTLED        = 'settled';

    public const BRAND_NAME_MAX_LENGTH = 60;
    public const BRAND_CODE_MAX_LENGTH = 6;

    /** Parent-to-child bootstrap order. Children never bind before their parent. */
    public const NODE_ORDER = [
        PlatformIdentifierPolicy::ACCOUNT,
        PlatformIdentifierPolicy::ACCOUNT_SETTINGS,
        PlatformIdentifierPolicy::ACCOUNT_TOOLS,
        PlatformIdentifierPolicy::ACCOUNT_PROFILE,
    ];

    /** @var array<string, string> entity type => fixed singleton native-reference address */
    public const NATIVE_REFERENCES = [
        PlatformIdentifierPolicy::ACCOUNT          => 'account:root',
        PlatformIdentifierPolicy::ACCOUNT_SETTINGS => 'account_settings:root',
        PlatformIdentifierPolicy::ACCOUNT_TOOLS    => 'account_tools:root',
        PlatformIdentifierPolicy::ACCOUNT_PROFILE  => 'account_profile:root',
    ];

    /** @return array<string, mixed> */
    public static function defaultState(): array
    {
        $nodes = [];
        foreach (self::NODE_ORDER as $entityType) {
            $nodes[$entityType] = ['platform_id' => null];
        }

        return [
            'version'                  => 1,
            'nodes'                    => $nodes,
            'platform_status'          => self::STATUS_DISABLED,
            'previous_platform_status' => '',
            'module_status'            => [self::MODULE_BRAND => self::MODULE_NOT_CONFIGURED],
            'brand'                    => self::emptyBrand(),
            'brand_draft'              => null,
            'media'                    => [],
        ];
    }

    /** @return array{name: string, code: string, logo_media_id: string|null, favicon_media_id: string|null} */
    public static function emptyBrand(): array
    {
        return [
            'name'             => '',
            'code'             => '',
            'logo_media_id'    => null,
            'favicon_media_id' => null,
        ];
    }
}
