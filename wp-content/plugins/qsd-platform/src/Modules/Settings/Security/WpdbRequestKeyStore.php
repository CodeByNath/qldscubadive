<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * WpdbRequestKeyStore — request-key records as non-autoloaded rows in the
 * WordPress options table, read and written through `$wpdb` directly.
 *
 * Why not the options API: one-time consumption needs a truly atomic claim.
 * `add_option()`/`delete_option()` check-then-act through the object cache, so
 * two concurrent requests can both "win". A row-level
 * `DELETE … WHERE option_name = %s` is atomic in the database: exactly one
 * caller sees one affected row. Rows bypass the options cache entirely and are
 * JSON-encoded (never unserialized).
 *
 * Row name: `qsd_rk_` + the SHA-256 hex of the key. The key is never stored.
 */
final class WpdbRequestKeyStore implements RequestKeyStore
{
    public const PREFIX = 'qsd_rk_';

    public function put(string $hash, array $record): void
    {
        global $wpdb;
        $inserted = $wpdb->insert($wpdb->options, [
            'option_name'  => self::PREFIX . $hash,
            'option_value' => (string) json_encode($record),
            'autoload'     => 'no',
        ]);
        if ($inserted !== 1) {
            throw new \RuntimeException('The request key could not be recorded.');
        }
    }

    public function find(string $hash): ?array
    {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            self::PREFIX . $hash
        ));
        return $this->decode($value);
    }

    public function claim(string $hash): bool
    {
        global $wpdb;
        return $wpdb->delete($wpdb->options, ['option_name' => self::PREFIX . $hash]) === 1;
    }

    public function sweepExpired(int $now): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like(self::PREFIX) . '%'
        ), ARRAY_A);

        $expired = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $record = $this->decode($row['option_value'] ?? null);
            if ($record === null || (int) ($record['expires_at'] ?? 0) < $now) {
                if ($wpdb->delete($wpdb->options, ['option_name' => $row['option_name']]) === 1 && $record !== null) {
                    $expired[] = $record;
                }
            }
        }
        return $expired;
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $record = json_decode($value, true);
        return is_array($record) ? $record : null;
    }
}
