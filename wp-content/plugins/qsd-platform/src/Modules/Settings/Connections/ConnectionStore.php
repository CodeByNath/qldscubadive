<?php

namespace QSD\Platform\Modules\Settings\Connections;

/**
 * ConnectionStore — the sole reader/writer of the provider connection option.
 *
 * Storage: one non-autoloaded option, keyed by provider:
 *   [provider => ['config' => [field => string], 'secrets' => [field => string], 'updated_at' => ISO-8601]]
 *
 * Only ConnectorCredentials (server-side consumers) and the Settings
 * Connections controller (write + safe projection) use this class. Nothing
 * else reads the option, and no REST response ever carries `secrets`.
 *
 * Secrets are stored as WordPress options store any value; at-rest encryption
 * is an open decision gate (docs/code-map/settings-station.md), not invented here.
 */
final class ConnectionStore
{
    public const OPTION = 'qsd_settings_connections';

    /** @return array{config: array<string, string>, secrets: array<string, string>, updated_at: ?string} */
    public function read(string $provider): array
    {
        $record = $this->all()[$provider] ?? [];
        return [
            'config'     => is_array($record['config'] ?? null) ? $record['config'] : [],
            'secrets'    => is_array($record['secrets'] ?? null) ? $record['secrets'] : [],
            'updated_at' => is_string($record['updated_at'] ?? null) ? $record['updated_at'] : null,
        ];
    }

    /** @param array{config: array<string, string>, secrets: array<string, string>} $record */
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
