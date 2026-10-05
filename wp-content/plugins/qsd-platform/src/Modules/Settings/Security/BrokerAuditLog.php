<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * BrokerAuditLog — the credential broker's safe audit trail.
 *
 * Entries carry only metadata: event, request id, provider, scope, caller,
 * user id, subject and timestamp. Never a request key, its hash, or any
 * provider secret. Bounded to the newest MAX entries in one non-autoloaded
 * option.
 */
final class BrokerAuditLog
{
    public const OPTION = 'qsd_settings_broker_audit';
    public const MAX    = 200;

    public const ISSUED           = 'issued';
    public const USED             = 'used';
    public const EXPIRED          = 'expired';
    public const REJECTED_UNKNOWN = 'rejected_unknown';
    public const REJECTED_REPLAY  = 'rejected_replay';
    public const REJECTED_EXPIRED = 'rejected_expired';
    public const REJECTED_BINDING = 'rejected_binding';
    public const FAILED           = 'failed';

    private const FIELDS = ['request_id', 'provider', 'scope', 'caller', 'user_id', 'subject'];

    /** @param array<string, mixed> $metadata */
    public function record(string $event, array $metadata, int $at): void
    {
        $entry = ['event' => $event, 'at' => gmdate('c', $at)];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $metadata)) {
                $entry[$field] = $metadata[$field];
            }
        }

        $entries = $this->entries();
        $entries[] = $entry;
        $entries = array_slice($entries, -self::MAX);
        if (!add_option(self::OPTION, $entries, '', 'no')) {
            update_option(self::OPTION, $entries, false);
        }
    }

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? array_values($value) : [];
    }
}
