<?php

namespace QSD\Platform\Modules\Account\Support;

use QSD\Platform\PlatformIdentifier\PlatformIdentifier;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierConflict;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierReservation;
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
 * A request that dies between `AccountRepository::writeNodePlatformId()` and
 * the registry's own bound-flip leaves that node's native storage holding an
 * id whose registry record is still `reserved`; plain `ensure()` treats that
 * as taken by someone else and throws rather than completing it. Before
 * calling `ensure()`, each node is checked for exactly that state and, if
 * found, finished with the Station's own `assign()` — re-validating the same
 * reservation and read-back, minting nothing new. Each node's parent id is
 * persisted alongside its own on first bind and re-checked against the
 * current bootstrap order on every resumption, so an aggregate corrupted
 * independently of this chain is caught rather than silently chained onto. A
 * node bound before parent linkage existed stores no parent id yet; that is
 * read as "not recorded", not as a mismatch.
 *
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
        $bound  = [];
        $parent = null;

        foreach (AccountSchema::NODE_ORDER as $entityType) {
            $platformId = $this->ensureNode($entityType, AccountSchema::NATIVE_REFERENCES[$entityType], $parent);
            $bound[$entityType] = $platformId;
            $parent = $platformId;
        }

        return $bound;
    }

    private function ensureNode(string $entityType, string $native, ?string $expectedParent): string
    {
        $read  = fn(int|string $nativeReference): string => $this->repository->readNodePlatformId($entityType);
        $write = fn(int|string $nativeReference, string $platformId): mixed =>
            $this->repository->writeNodePlatformId($entityType, $platformId, $expectedParent);

        $stored = $read($native);
        if ($stored !== '' && $this->platformIdentifiers->resolve($stored)?->status() === PlatformIdentifierStation::STATUS_RESERVED) {
            $this->platformIdentifiers->assign(
                new PlatformIdentifierReservation(new PlatformIdentifier($entityType, $stored)),
                $native,
                $read,
                $write
            );
        }

        $binding = $this->platformIdentifiers->ensure($entityType, $native, $read, $write);

        $storedParent = $this->repository->readNodeParentPlatformId($entityType);
        if ($storedParent !== null && $storedParent !== $expectedParent) {
            throw PlatformIdentifierConflict::registry(
                "Account node '{$entityType}' names a different parent than the current bootstrap order."
            );
        }

        return $binding->platformId();
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
