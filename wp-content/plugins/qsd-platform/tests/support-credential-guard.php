<?php

declare(strict_types=1);

// Shared test doubles for the credential mutation guard: a row-addressed
// options table with the unique-key insert and value-matched delete the real
// WpdbCredentialMutationGuard relies on, and a pass-through guard for tests
// that do not exercise concurrency. Loaded by tests, never run on its own.

if (!class_exists('GuardWpdb')) {
    final class GuardWpdb
    {
        public string $options = 'wp_options';
        /** @var array<string, string> option_name => option_value */
        public array $rows = [];

        public function insert(string $table, array $data): int|false
        {
            if (isset($this->rows[$data['option_name']])) return false; // unique key
            $this->rows[$data['option_name']] = $data['option_value'];
            return 1;
        }
        public function prepare(string $query, mixed ...$args): array { return [$query, $args]; }
        public function get_var(array $prepared): ?string { return $this->rows[$prepared[1][0]] ?? null; }
        public function delete(string $table, array $where): int
        {
            $name = $where['option_name'];
            if (!isset($this->rows[$name]) || (isset($where['option_value']) && $this->rows[$name] !== $where['option_value'])) return 0;
            unset($this->rows[$name]);
            return 1;
        }
    }
}
