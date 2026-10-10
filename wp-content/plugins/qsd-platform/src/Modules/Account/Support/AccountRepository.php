<?php

namespace QSD\Platform\Modules\Account\Support;

/**
 * AccountRepository — the one Account-owned WordPress options row
 * (`AccountSchema::OPTION_KEY`), read and written through `$wpdb` directly,
 * never through `get_option()`/`update_option()`.
 *
 * Why: Account is a single aggregate record, not a collection, so every write
 * is a read-modify-write of the whole row. The options API's cache makes
 * check-then-act races possible; a raw `UPDATE … WHERE option_value = <exact
 * prior bytes>` compare-and-swap is atomic in the database, so exactly one of
 * two racing writers lands and the other sees zero affected rows and retries
 * against the fresh row. `BINARY` forces an exact byte comparison — the
 * options table's default collation is case/pad-insensitive, which would
 * otherwise let a different-but-equal-looking value pass as a match.
 *
 * `commit()` is the only write path; every mutation (identity bootstrap, Brand
 * draft/settle, lifecycle) goes through it so no caller can bypass the CAS
 * loop with a plain `update_option()` call.
 */
final class AccountRepository
{
    private const MAX_ATTEMPTS = 40;
    private const MIN_DELAY_MICROSECONDS = 500;
    private const MAX_DELAY_MICROSECONDS = 20000;

    /** @return array{0: array<string, mixed>, 1: ?string} [state, exact raw bytes currently stored, or null if the row does not exist yet] */
    public function read(): array
    {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            AccountSchema::OPTION_KEY
        ));

        if (!is_string($raw)) {
            return [AccountSchema::defaultState(), null];
        }

        $state = json_decode($raw, true);

        return [is_array($state) ? $state : AccountSchema::defaultState(), $raw];
    }

    /**
     * Apply a pure mutator to the current state and persist the result
     * atomically, retrying against a fresh read on every lost race.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $mutate
     * @return array<string, mixed> the state actually committed
     */
    public function commit(\Closure $mutate): array
    {
        global $wpdb;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            [$state, $raw] = $this->read();
            $next    = $mutate($state);
            $encoded = (string) json_encode($next);

            if ($raw === null) {
                $affected = $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                    AccountSchema::OPTION_KEY,
                    $encoded
                ));
            } else {
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s",
                    $encoded,
                    AccountSchema::OPTION_KEY,
                    $raw
                ));
            }

            if ($affected === 1) {
                return $next;
            }

            usleep(random_int(self::MIN_DELAY_MICROSECONDS, self::MAX_DELAY_MICROSECONDS));
        }

        throw new AccountStorageBusy();
    }

    public function readNodePlatformId(string $entityType): string
    {
        [$state] = $this->read();
        $stored = $state['nodes'][$entityType]['platform_id'] ?? null;

        return is_string($stored) ? $stored : '';
    }

    public function writeNodePlatformId(string $entityType, string $platformId): void
    {
        $this->commit(static function (array $state) use ($entityType, $platformId): array {
            $state['nodes'][$entityType]['platform_id'] = $platformId;
            return $state;
        });
    }

    /** @return array<string, array{platform_id: string|null}> */
    public function nodes(): array
    {
        [$state] = $this->read();

        return is_array($state['nodes'] ?? null) ? $state['nodes'] : AccountSchema::defaultState()['nodes'];
    }
}
