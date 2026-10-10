# Account Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

This module owns the Account singleton hierarchy: Account → Settings → Tools → Profile (Brand). Exactly one of each ever exists per site — there is no catalogue, no list, and no numeric native reference.

- `AccountModule.php` — module wiring.
- `Http/AccountController.php` — Account routes: read-only detail, the idempotent identity bootstrap, Brand draft save/settle, media upload/library, and the Publish/Disable/Enable status route. No frontend registration, surface, card, or drawer exists yet (Phase C).
- `Support/AccountSchema.php` — the one options-row storage key, the four nodes' parent-to-child order and fixed native-reference addresses, the Brand/lifecycle default shape, and field-length constants.
- `Support/AccountRepository.php` — the sole read/write path for that row: a `$wpdb`-level compare-and-swap commit loop. No other file may call `get_option`/`update_option` on `AccountSchema::OPTION_KEY`.
- `Support/AccountIdentity.php` — sequences the four nodes through the shared `PlatformIdentifierStation::ensure()`. The only identity-minting entry point; `fetchDetail` never mints.
- `Support/AccountBrand.php` — Brand field sanitization and the draft/settle/publish/disable/enable lifecycle rules (a narrow two-state slice reimplemented directly, not the full `StationLifecycle` engine — Account never uses `draft`/`archived`/`trashed`). Disable/Enable mirror Service's mask pattern exactly: Enable always lands back on unmasked `disabled` (Pending), never straight to `active`.
- `Support/AccountMedia.php` — content-hash-addressed Brand logo/favicon storage (own uploads subdirectory, not the WordPress Media Library). Content-sniffed against an allow-list, size-capped before the file is read, and upload-origin-verified; nothing already stored is ever deleted.
- `Core\Plugin` injects the shared `PlatformIdentifierStation`; the four Platform ID prefixes (`QSDA`/`QSDAS`/`QSDAST`/`QSDASTP`) are declared once in `PlatformIdentifierPolicy` and are permanent once any record exists.

## Boundaries

No Archive/Trash/Restore/Delete route exists for Account and none should be added without an explicit, separately authorised Owner decision — this is a deliberate singleton carve-out from the Station/Drawer lifecycle contract's travel-action table, not an oversight. Never accept `platform_id`/`platformId` in a write payload; identity is output-only. Never build a second reservation/repair mechanism — every identity write goes through the shared `PlatformIdentifierStation`. Never read or write Settings/Security's own storage from here; Phase D only relocates *presentation* of the existing Settings panels under Account, never their backend. Brand media is never routed through the WordPress Media Library and never deleted on replacement — it is content-addressed and may still be referenced by the catalogue or a prior draft.

Read [Station and Drawer Lifecycle Contract v1](../../../../../../docs/architecture/StationDrawerLifecycleContract-v1.md) and [Platform Identifier Station](../../../../../../docs/code-map/platform-identifier-station.md).

## Validation

From the plugin root: `npm test` (includes `tests/account-station.php`, `tests/account-brand.php`, and the updated `tests/platform-identifier-station.php`) and `npm run docs:check`.
