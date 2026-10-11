<?php

declare(strict_types=1);

// Shared test double for AccountRepository: the `wp_options` table as the real
// AccountRepository sees it through `$wpdb` directly (never through
// get_option()/update_option()/the object cache). Backs exactly the three
// statement shapes AccountRepository issues: a keyed SELECT, an INSERT IGNORE
// first-create, and a BINARY-exact compare-and-swap UPDATE — including real
// MySQL's "0 affected rows when the SET value is unchanged" quirk, not just a
// WHERE-matched/unmatched boolean. Loaded by tests, never run on its own.

if (!class_exists('AccountOptionsTable')) {
    final class AccountOptionsTable
    {
        /** @var array<string, string> option_name => option_value (raw bytes) */
        public array $rows = [];

        /** When true, every UPDATE reports zero affected rows regardless of match — simulates a permanently busy row. */
        public bool $alwaysStale = false;
    }

    final class AccountWpdb
    {
        public string $options = 'wp_options';

        public function __construct(private AccountOptionsTable $table)
        {
        }

        public function prepare(string $query, mixed ...$args): array
        {
            return [$query, $args];
        }

        public function get_var(string|array $query): ?string
        {
            [$sql, $args] = is_array($query) ? $query : [$query, []];
            if (str_starts_with($sql, 'SELECT option_value FROM')) {
                $name = $args[0];
                return $this->table->rows[$name] ?? null;
            }
            throw new LogicException("AccountWpdb::get_var cannot answer: {$sql}");
        }

        /** @return int affected row count */
        public function query(string|array $query): int
        {
            [$sql, $args] = is_array($query) ? $query : [$query, []];

            if (str_starts_with($sql, 'INSERT IGNORE INTO')) {
                [$name, $value] = $args;
                if (array_key_exists($name, $this->table->rows)) {
                    return 0;
                }
                $this->table->rows[$name] = $value;
                return 1;
            }

            if (str_starts_with($sql, 'UPDATE')) {
                [$value, $name, $expected] = $args;
                if ($this->table->alwaysStale) {
                    return 0;
                }
                if (!array_key_exists($name, $this->table->rows) || $this->table->rows[$name] !== $expected) {
                    return 0;
                }
                // Real MySQL reports 0 affected rows when the SET value is byte-identical
                // to the row's current value, even though the WHERE matched — WordPress's
                // $wpdb never requests the CLIENT_FOUND_ROWS connection flag. A caller that
                // treats "0 affected" as always meaning "lost the race" cannot tell this
                // apart from a real conflict; mirroring it here is what makes that gap
                // reproducible under test instead of only on a live database.
                if ($this->table->rows[$name] === $value) {
                    return 0;
                }
                $this->table->rows[$name] = $value;
                return 1;
            }

            throw new LogicException("AccountWpdb::query cannot answer: {$sql}");
        }
    }
}
