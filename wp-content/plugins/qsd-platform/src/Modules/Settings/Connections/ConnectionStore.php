<?php

namespace QSD\Platform\Modules\Settings\Connections;

/**
 * ConnectionStore — the sole reader/writer of the provider connection option.
 *
 * Storage: one non-autoloaded option, keyed by provider:
 *   [provider => ['config' => [field => string], 'secrets' => [field => envelope], 'updated_at' => ISO-8601]]
 *
 * Only ConnectorCredentials, the Settings Connections controller and
 * Security\CredentialRotation use this class. Nothing else reads the option, and no REST response ever carries
 * `secrets`.
 *
 * Every secret is stored as a Security\CredentialCipher envelope (encrypted at
 * rest under the CredentialKeyring data key); this class never sees a plaintext secret.
 */
final class ConnectionStore
{
    public const OPTION = 'qsd_settings_connections';

    /** @return array{config: array<string, string>, secrets: array<string, mixed>, updated_at: ?string} */
    public function read(string $provider): array
    {
        $record = $this->all()[$provider] ?? [];
        return [
            'config'     => is_array($record['config'] ?? null) ? $record['config'] : [],
            'secrets'    => is_array($record['secrets'] ?? null) ? $record['secrets'] : [],
            'updated_at' => is_string($record['updated_at'] ?? null) ? $record['updated_at'] : null,
        ];
    }

    /** @param array{config: array<string, string>, secrets: array<string, mixed>} $record */
    public function write(string $provider, array $record): void
    {
        $all = $this->all();
        $all[$provider] = [
            'config'     => $record['config'],
            'secrets'    => $record['secrets'],
            'updated_at' => gmdate('c'),
        ];
        $this->persist($all);
    }

    /** @return list<string> every provider with a stored record */
    public function providers(): array
    {
        return array_map('strval', array_keys($this->all()));
    }

    /**
     * Replaces the named secret envelopes in one write (key rotation). Config,
     * other secrets and `updated_at` are left as they are.
     *
     * @param array<string, array<string, mixed>> $secretsByProvider provider => [field => envelope]
     */
    public function replaceSecrets(array $secretsByProvider): void
    {
        $all = $this->all();
        foreach ($secretsByProvider as $provider => $secrets) {
            if (!is_array($all[$provider] ?? null)) {
                continue;
            }
            foreach ($secrets as $field => $envelope) {
                $all[$provider]['secrets'][$field] = $envelope;
            }
        }
        $this->persist($all);
    }

    public function remove(string $provider): void
    {
        $all = $this->all();
        unset($all[$provider]);
        $this->persist($all);
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $all */
    private function persist(array $all): void
    {
        if (!add_option(self::OPTION, $all, '', 'no')) {
            update_option(self::OPTION, $all, false);
        }
    }
}
