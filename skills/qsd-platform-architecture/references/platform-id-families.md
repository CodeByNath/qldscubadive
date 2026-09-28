# Platform ID Families

Source of truth:
`wp-content/plugins/qsd-platform/src/PlatformIdentifier/PlatformIdentifierPolicy.php`.
Read it directly before relying on this table — it is the only place a
prefix is defined; this file is a snapshot.

An identifier is the prefix plus five characters from a 30-character
alphabet that excludes `0 1 I L O U`. Validation is one anchored regex
requiring the **full** string to be exactly `prefix + 5` characters, so
prefixes that share a stem can never be confused: a longer real ID can
never satisfy a shorter prefix's pattern.

| Prefix | Entity type | Native reference | Owner | Rung |
|---|---|---|---|---|
| `QSDS` | `service` | `qsd_service` post ID | Service Station | 3 |
| `QSDC` | `category` | `qsd_service_category` term ID | Category Station | 3 |

Service Inclusion and FAQ pool items have stable string ids inside their
Service (rung 2) and deliberately no Platform ID: nothing outside the
Service addresses them yet. Another Station that must reference them does
so through `ServicePools` and the `qsd_service_pool_references` filter.

## How to extend this vocabulary

1. Confirm the new concept is genuinely rung 3 (see
   `identity-composition-model.md`) — rung 2 needs a new family only if
   something must address it by Platform ID; rung 1 needs none.
2. Add one `const` and one `PREFIXES` entry to `PlatformIdentifierPolicy`,
   starting with `QSD` (e.g. a four- or five-letter code). Prefixes are
   permanent once any record exists.
3. In the owning Station's controller: `reserve()` before the native
   insert, `assign()` after it (retire the reservation on failure),
   `ensure()` where an existing record may lack one, `markDeleted()` on
   permanent delete. Store the ID in the Station's own `qsd_platform_id`
   meta and project it as output-only `platform_id`; reject it in every
   write payload.
4. Add an authenticated by-ID read route that resolves through the engine
   and calls the owner's normal projection.
5. Update `tests/platform-identifier-station.php`'s expected prefixes and
   run `npm run contract:platform-identity-schema`, which fails if any
   source, contract, or doc names a prefix the policy does not define.
