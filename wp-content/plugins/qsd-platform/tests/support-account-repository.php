<?php

declare(strict_types=1);

// Shared test double for AccountRepository: the `wp_options` table as the real
// AccountRepository sees it through `$wpdb` directly (never through
// get_option()/update_option()/the object cache). Backs exactly the three
// statement shapes AccountRepository issues: a keyed SELECT, an INSERT IGNORE
// first-create, and a BINARY-exact compare-and-swap UPDATE. Loaded by tests,
// never run on its own.

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
                $this->table->rows[$name] = $value;
                return 1;
            }

            throw new LogicException("AccountWpdb::query cannot answer: {$sql}");
        }
    }
}
