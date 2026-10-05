<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * Persistence for issued request keys. Records are addressed by the SHA-256
 * hash of the key — the key itself is never stored — and carry only safe
 * metadata (request id, provider, scope, caller binding, timestamps).
 */
interface RequestKeyStore
{
    /** @param array<string, mixed> $record */
    public function put(string $hash, array $record): void;

    /** @return array<string, mixed>|null */
    public function find(string $hash): ?array;

    /**
     * Atomically removes the record. Exactly one concurrent caller gets true;
     * every other caller — including a replay — gets false.
     */
    public function claim(string $hash): bool;

    /**
     * Removes and returns every record that expired before `$now`.
     *
     * @return list<array<string, mixed>>
     */
    public function sweepExpired(int $now): array;
}
