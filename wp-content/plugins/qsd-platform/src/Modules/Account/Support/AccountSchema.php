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
            'version' => 1,
            'nodes'   => $nodes,
        ];
    }
}
