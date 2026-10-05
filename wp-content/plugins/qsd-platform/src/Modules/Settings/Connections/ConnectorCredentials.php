<?php

namespace QSD\Platform\Modules\Settings\Connections;

/**
 * ConnectorCredentials — the controlled server-side capability a Connector
 * uses to read its own provider configuration and secrets.
 *
 * Domain Stations and Connectors never read ConnectionStore or the option
 * directly; they receive this capability and ask for their own provider's
 * values. It has no REST surface and never feeds a projection.
 *
 * Short-lived, scoped, single-use rotating request keys are NOT implemented:
 * that mechanism is a recorded security decision gate. Today a Connector gets
 * the stored long-lived provider credential only, server-side.
 */
final class ConnectorCredentials
{
    public function __construct(private ConnectionStore $store) {}

    public function secret(string $provider, string $field): ?string
    {
        $value = $this->store->read($provider)['secrets'][$field] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function config(string $provider, string $field): ?string
    {
        $value = $this->store->read($provider)['config'][$field] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Every required field of the provider has a stored value. */
    public function isConfigured(ConnectionProviderDefinition $definition): bool
    {
        foreach ($definition->fields as $field) {
            if (!$field['required']) {
                continue;
            }
            $value = $definition->isSecret($field['key'])
                ? $this->secret($definition->key, $field['key'])
                : $this->config($definition->key, $field['key']);
            if ($value === null) {
                return false;
            }
        }
        return true;
    }
}
