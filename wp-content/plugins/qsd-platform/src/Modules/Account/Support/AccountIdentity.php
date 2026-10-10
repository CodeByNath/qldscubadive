<?php

namespace QSD\Platform\Modules\Account\Support;

use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

/**
 * AccountIdentity — idempotent bootstrap of the Account singleton hierarchy's
 * four permanent Platform identities (Account → Settings → Tools → Profile).
 *
 * Each node is addressed by a fixed native-reference string
 * (`AccountSchema::NATIVE_REFERENCES`), never a numeric id, because exactly
 * one of each ever exists per site. `PlatformIdentifierStation::ensure()` is
 * itself idempotent and safe under races: a node whose id is already stored is
 * re-affirmed, not re-minted; a node with no stored id is reserved and bound,
 * and a racing second caller's write loses the registry's compare-and-swap and
 * throws `PlatformIdentifierConflict` rather than silently binding a second
 * id. Nodes are processed strictly in parent-to-child order so a child is
 * never addressed before its parent exists.
 *
 * A request that dies partway through `bootstrap()` leaves the already-bound
 * nodes untouched; the next call resumes from the first unbound node, because
 * `ensure()` reads each node's current state before deciding whether to mint.
 * This file never reads a `platform_id` on a write-path payload and never
 * invents a parallel reservation mechanism — it only sequences calls into the
 * one shared `PlatformIdentifierStation`.
 */
final class AccountIdentity
{
    public function __construct(
        private PlatformIdentifierStation $platformIdentifiers,
        private AccountRepository $repository
    ) {
    }

    /** @return array<string, string> entity type => bound platform id, in AccountSchema::NODE_ORDER */
    public function bootstrap(): array
    {
        $bound = [];

        foreach (AccountSchema::NODE_ORDER as $entityType) {
            $native = AccountSchema::NATIVE_REFERENCES[$entityType];

            $binding = $this->platformIdentifiers->ensure(
                $entityType,
                $native,
                fn(int|string $nativeReference): string => $this->repository->readNodePlatformId($entityType),
                fn(int|string $nativeReference, string $platformId): mixed =>
                    $this->repository->writeNodePlatformId($entityType, $platformId)
            );

            $bound[$entityType] = $binding->platformId();
        }

        return $bound;
    }

    /** Read-only: current node state, never minting. @return array<string, string|null> */
    public function nodes(): array
    {
        $nodes = $this->repository->nodes();
        $result = [];

        foreach (AccountSchema::NODE_ORDER as $entityType) {
            $result[$entityType] = $nodes[$entityType]['platform_id'] ?? null;
        }

        return $result;
    }

    public function isBootstrapped(): bool
    {
        foreach ($this->nodes() as $platformId) {
            if ($platformId === null) {
                return false;
            }
        }

        return true;
    }
}
