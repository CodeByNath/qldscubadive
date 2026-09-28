# Platform Identifier Station

## Purpose and boundary

`PlatformIdentifierStation` is backend infrastructure for permanent, globally
unique identity: policy, secure generation, atomic reservation, immutable
binding, forward/reverse lookup, deletion tombstones, and conflict detection.

It owns no native entity, lifecycle, validation, draft, projection,
relationship, drawer, or domain action. Owners supply scalar identity
read/write callbacks. It is not the frontend Station Manager and must never
register there.

## Authoritative files

- `src/PlatformIdentifier/PlatformIdentifierStation.php` — engine and minimal
  non-autoloaded WordPress Option registry.
- `src/PlatformIdentifier/PlatformIdentifierPolicy.php` — closed entity types,
  prefixes, alphabet, suffix length, and validation.
- `src/PlatformIdentifier/PlatformIdentifier.php` and
  `PlatformIdentifierReservation.php` — validated candidate/reservation values.
- `src/PlatformIdentifier/PlatformIdentifierBinding.php` and
  `PlatformIdentifierBatchResult.php` — lookup and bounded-assignment results.
- `src/PlatformIdentifier/PlatformIdentifierConflict.php` — fail-closed
  contract failures.
- `tests/platform-identifier-station.php` — engine contract.
- `wp-content/plugins/qsd-platform/scripts/platform-identity-schema-contract.ts`
  — frontend identity schema, plus the vocabulary lock below.

## Current vocabulary

| Entity type | Prefix | Owner |
| --- | --- | --- |
| `service` | `QSDS` | Service Station (`qsd_platform_id` post meta) |
| `category` | `QSDC` | Category Station (`qsd_platform_id` term meta) |

An identifier is the prefix plus five characters from a 30-character
alphabet that excludes `0 1 I L O U`. Prefixes are permanent once records
exist. A new Station adds exactly one entry to the policy, then wires its
own reservation/binding in its controller, mirroring Service.

## Vocabulary lock

`PlatformIdentifierPolicy` is the only place a prefix is defined. Frontend
sources, contracts, and Code Maps consume that vocabulary and never coin one.
`npm run contract:platform-identity-schema` reads the prefixes, alphabet, and
suffix length from the policy and scans `resources/ts`, `scripts`, `docs`, and `skills`.
A token must be exactly a canonical prefix, or one plus a full-length suffix.

## Registry contract

Forward options are `qsd_platform_identifier_v1_{platformId}`. Reverse options
are `qsd_platform_identifier_native_v1_{entityType}_{typed-reference-hash}`.
Every option is non-autoloaded. Records carry version, Platform ID, entity
type, native reference, `reserved|bound|retired|deleted` status, and
timestamps. Reservations and tombstones are never deleted or reused.

## Integration

`Core\Plugin` constructs one Station and injects it through `ServiceModule`
and `AdminModule`. Creation reserves before the native insert, binds after
it, and retires the reservation on failure. Permanent deletion writes a
tombstone, so a deleted record's ID can never be reissued. Authenticated
by-ID reads (`/admin/services/QSDS…`, `/admin/categories/QSDC…`) resolve here,
reject non-bound or wrong-entity bindings, then call the owner's projection
by native numeric ID. The ID is output-only: every write route rejects a
`platform_id` payload.

## Related Code Maps

[Service Station](service-station.md), [Categories](categories.md).
