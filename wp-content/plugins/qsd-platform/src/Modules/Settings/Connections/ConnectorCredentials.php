<?php

namespace QSD\Platform\Modules\Settings\Connections;

use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialKeyring;

/**
 * ConnectorCredentials — Settings' internal read path for stored provider
 * configuration and (decrypted) secrets.
 *
 * Only Settings code holds this: the Connections controller's projection asks
 * whether a secret is usable, Connectors read their non-secret configuration,
 * and Security\CredentialBroker is the ONLY caller of `secret()` — it decrypts
 * a credential server-side after a request key is consumed and hands it to a
 * Settings-owned provider operation. No domain Station or other consumer
 * receives this class; they go through the broker and never see a secret.
 * It has no REST surface and never feeds a projection.
 */
final class ConnectorCredentials
{
    public function __construct(private ConnectionStore $store, private CredentialKeyring $keyring) {}

    /** The decrypted secret, or null when absent, sealed under a key the keyring cannot open, or tampered. */
    public function secret(string $provider, string $field): ?string
    {
        $envelope = $this->store->read($provider)['secrets'][$field] ?? null;
        $value = $this->keyring->cipher()->open($envelope, CredentialCipher::context($provider, $field));
        return $value !== null && $value !== '' ? $value : null;
    }

    /** A stored secret decrypts under the keyring — without returning it. */
    public function hasSecret(string $provider, string $field): bool
    {
        return $this->secret($provider, $field) !== null;
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
            $present = $definition->isSecret($field['key'])
                ? $this->hasSecret($definition->key, $field['key'])
                : $this->config($definition->key, $field['key']) !== null;
            if (!$present) {
                return false;
            }
        }
        return true;
    }
}
