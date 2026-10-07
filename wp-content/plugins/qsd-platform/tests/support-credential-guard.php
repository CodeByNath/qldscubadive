<?php

declare(strict_types=1);

// Shared test doubles for the credential mutation guard: MySQL named locks as
// the real WpdbCredentialMutationGuard relies on them. A lock is server-wide,
// owned by one connection, re-entrant for that connection, and freed when the
// connection ends. GET_LOCK against another connection's lock answers 0 at
// once (the wait timing out). Locks have no expiry. Loaded by tests, never run
// on its own.

if (!class_exists('GuardLockServer')) {
    final class GuardLockServer
    {
        /** @var array<string, array{0: int, 1: int}> lock name => [owning connection id, depth] */
        public array $locks = [];
        public int $nextConnection = 1;
    }

    trait NamedLockQueries
    {
        public int $reconnect_retries = 5;
        public GuardLockServer $lockServer;
        public int $connectionId;

        public function useLockServer(GuardLockServer $server): static
        {
            $this->lockServer = $server;
            $this->connectionId = $server->nextConnection++;
            return $this;
        }

        /** Ends this connection as a crash or timeout does: its locks are freed; a reconnect gets a new id. */
        public function dropConnection(): void
        {
            foreach ($this->lockServer->locks as $name => [$owner]) {
                if ($owner === $this->connectionId) unset($this->lockServer->locks[$name]);
            }
            $this->connectionId = $this->lockServer->nextConnection++;
        }

        /** @return array{0: bool, 1: ?string} whether the query was a lock query, and its value */
        private function lockQuery(string|array $query): array
        {
            [$sql, $args] = is_array($query) ? $query : [$query, []];
            $locks = &$this->lockServer->locks;
            if ($sql === 'SELECT CONNECTION_ID()') return [true, (string) $this->connectionId];
            if (str_starts_with($sql, 'SELECT IS_USED_LOCK(')) return [true, isset($locks[$args[0]]) ? (string) $locks[$args[0]][0] : null];
            if (str_starts_with($sql, 'SELECT GET_LOCK(')) {
                $held = $locks[$args[0]] ?? null;
                if ($held !== null && $held[0] !== $this->connectionId) return [true, '0'];
                $locks[$args[0]] = [$this->connectionId, ($held[1] ?? 0) + 1];
                return [true, '1'];
            }
            if (str_starts_with($sql, 'SELECT RELEASE_LOCK(')) {
                $held = $locks[$args[0]] ?? null;
                if ($held === null || $held[0] !== $this->connectionId) return [true, '0'];
                if ($held[1] > 1) $locks[$args[0]][1]--; else unset($locks[$args[0]]);
                return [true, '1'];
            }
            return [false, null];
        }
    }

    final class GuardWpdb
    {
        use NamedLockQueries;

        public string $options = 'wp_options';

        public function __construct(?GuardLockServer $server = null)
        {
            $this->useLockServer($server ?? new GuardLockServer());
        }
        public function prepare(string $query, mixed ...$args): array { return [$query, $args]; }
        public function get_var(string|array $query): ?string
        {
            [$handled, $value] = $this->lockQuery($query);
            if (!$handled) throw new LogicException('GuardWpdb answers lock queries only.');
            return $value;
        }
    }
}
