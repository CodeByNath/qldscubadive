<?php

namespace QSD\Platform\Modules\Settings\Connections;

/**
 * One provider's connection declaration — provider-neutral.
 *
 * A Connector (Rezdy today) declares its key, labels, and the configuration
 * fields an administrator supplies. Field types:
 *   text   — non-secret configuration, projected back to the admin frontend;
 *   select — non-secret configuration constrained to `options` (value => label);
 *   secret — a credential; stored server-side and NEVER projected back.
 *
 * Settings stores and projects any provider through this shape alone; no
 * Settings class branches on a provider key.
 *
 * `scopes` is the closed list of narrow authorities the credential broker may
 * issue request keys for (Security\CredentialBroker). A provider with no
 * declared scope cannot be brokered at all.
 */
final class ConnectionProviderDefinition
{
    public const FIELD_TEXT   = 'text';
    public const FIELD_SELECT = 'select';
    public const FIELD_SECRET = 'secret';

    /**
     * @param list<array{key: string, label: string, type: string, required: bool, options?: array<string, string>}> $fields
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $fields,
        public readonly array $scopes = [],
    ) {}

    /** @return array{key: string, label: string, type: string, required: bool, options?: array<string, string>}|null */
    public function field(string $key): ?array
    {
        foreach ($this->fields as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }
        return null;
    }

    public function isSecret(string $key): bool
    {
        return ($this->field($key)['type'] ?? null) === self::FIELD_SECRET;
    }
}
