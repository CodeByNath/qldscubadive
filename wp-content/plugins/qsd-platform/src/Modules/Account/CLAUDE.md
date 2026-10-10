# Account Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

This module owns the Account singleton hierarchy: Account → Settings → Tools → Profile (Brand). Exactly one of each ever exists per site — there is no catalogue, no list, and no numeric native reference.

- `AccountModule.php` — module wiring.
- `Http/AccountController.php` — Account routes. Phase A only: read-only detail and the idempotent identity bootstrap. No Brand field, draft, settle, or Publish/Disable/Enable route exists yet (Phase B).
- `Support/AccountSchema.php` — the one options-row storage key, the four nodes' parent-to-child order, and their fixed native-reference addresses.
- `Support/AccountRepository.php` — the sole read/write path for that row: a `$wpdb`-level compare-and-swap commit loop. No other file may call `get_option`/`update_option` on `AccountSchema::OPTION_KEY`.
- `Support/AccountIdentity.php` — sequences the four nodes through the shared `PlatformIdentifierStation::ensure()`. The only identity-minting entry point; `fetchDetail` never mints.
- `Core\Plugin` injects the shared `PlatformIdentifierStation`; the four Platform ID prefixes (`QSDA`/`QSDAS`/`QSDAST`/`QSDASTP`) are declared once in `PlatformIdentifierPolicy` and are permanent once any record exists.

## Boundaries

No Archive/Trash/Restore/Delete route exists for Account and none should be added without an explicit, separately authorised Owner decision — this is a deliberate singleton carve-out from the Station/Drawer lifecycle contract's travel-action table, not an oversight. Never accept `platform_id`/`platformId` in a write payload; identity is output-only. Never build a second reservation/repair mechanism — every identity write goes through the shared `PlatformIdentifierStation`. Never read or write Settings/Security's own storage from here; Phase D only relocates *presentation* of the existing Settings panels under Account, never their backend.

Read [Station and Drawer Lifecycle Contract v1](../../../../../../docs/architecture/StationDrawerLifecycleContract-v1.md) and [Platform Identifier Station](../../../../../../docs/code-map/platform-identifier-station.md).

## Validation

From the plugin root: `npm test` (includes `tests/account-station.php` and the updated `tests/platform-identifier-station.php`) and `npm run docs:check`.
