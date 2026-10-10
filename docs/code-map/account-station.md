# Account Station

Account is a singleton hierarchy — Account → Settings → Tools → Profile (Brand) — not a repeatable list entity. Exactly one of each node ever exists per site. It is a deliberate, Owner-approved deviation from the locked [Station and Drawer Lifecycle Contract](../architecture/StationDrawerLifecycleContract-v1.md): Publish/Disable/Enable conform exactly, but no Archive/Trash/Restore/Delete route, action, or drawer control exists, and none should be added without a separate, explicit Owner decision. This is stated here rather than claimed as full §7 conformance.

## Purpose and ownership

`src/Modules/Account/` is the single backend owner. Four permanent Platform ID prefixes — `QSDA`/`QSDAS`/`QSDAST`/`QSDASTP` (Account/Settings/Tools/Profile) — are declared once in `PlatformIdentifierPolicy`; each nests as a string prefix of the next but disambiguates by total length, so none can collide with a sibling or an existing prefix. Identity for all four nodes is bootstrapped idempotently through the shared `PlatformIdentifierStation::ensure()` — never a parallel reservation mechanism — addressed by fixed native-reference strings (`account:root`, etc.), since each node is a singleton rather than a numeric/string native record.

- [AccountRepository.php](../../wp-content/plugins/qsd-platform/src/Modules/Account/Support/AccountRepository.php) is the one aggregate's sole read/write path: a single non-autoloaded WordPress options row, written only through a `$wpdb`-level compare-and-swap commit loop (never `get_option`/`update_option` directly), retried against a fresh read on every lost race, failing closed with `AccountStorageBusy` when the retry budget is exhausted.
- [AccountIdentity.php](../../wp-content/plugins/qsd-platform/src/Modules/Account/Support/AccountIdentity.php) sequences the four nodes parent-to-child; a request that dies mid-bootstrap leaves already-bound nodes untouched for the next call to finish.
- [AccountBrand.php](../../wp-content/plugins/qsd-platform/src/Modules/Account/Support/AccountBrand.php) is the Brand module's lifecycle rules, reimplemented as a narrow two-state (`active`/`disabled`) slice rather than the full `StationLifecycle` engine, since Account never uses `draft`/`archived`/`trashed`. Brand has no required field, so it always settles cleanly. Disable/Enable mirror Service's `updateDisabledMask` exactly: Enable always lands back on unmasked `disabled` (Pending), never straight to `active`.
- [AccountMedia.php](../../wp-content/plugins/qsd-platform/src/Modules/Account/Support/AccountMedia.php) stores Brand logo/favicon uploads content-hash-addressed in their own uploads subdirectory — never the WordPress Media Library. Content is sniffed via `getimagesizefromstring()` against a four-type allow-list; nothing stored is ever deleted.
- [AccountController.php](../../wp-content/plugins/qsd-platform/src/Modules/Account/Http/AccountController.php) owns every route: read-only detail, idempotent bootstrap, Brand draft save/settle, media upload/library, and the Publish (`platform_status=active`, settle-then-activate in one call) / Disable / Enable status route.

## Frontend

`resources/ts/account-station/` registers Account with Station Manager exactly as Service does, with a card surface instead of a catalogue (there is nothing to list):

- [register.ts](../../wp-content/plugins/qsd-platform/resources/ts/account-station/register.ts) registers the nav item, a `mode: 'card'` destination, the `account` data source, the `account-card` template kit, and the `account` drawer template. Admin authors the actual presentation placement in `admin-station/register.ts`'s `registerPresentationPolicy()`, exactly as it does for Service.
- [useAccountCard.ts](../../wp-content/plugins/qsd-platform/resources/ts/account-station/surface/useAccountCard.ts) and [AccountCard.tsx](../../wp-content/plugins/qsd-platform/resources/ts/account-station/presentation/AccountCard.tsx) are the Home surface: one `ReadBlock`, no grid. Deliberately a separate read from the drawer's own fetch, the same two-instance rule Service keeps.
- [AccountDrawerHost.tsx](../../wp-content/plugins/qsd-platform/resources/ts/account-station/surface/AccountDrawerHost.tsx) implements the drawer content directly — Account's one module needs no generic multi-module `EntityDrawer`/schema composition. Read mode is a `ReadBlock`; Edit mode is `InlineEditorShell` over [AccountBrandEditor.tsx](../../wp-content/plugins/qsd-platform/resources/ts/account-station/drawer/AccountBrandEditor.tsx) (Name, Code, and the two media pickers — file upload is not an `AdminFieldType`, so each picker is its own small control). The footer is a direct `EntityActionFooter` composition (Disable/Enable split with an empty overflow, Publish primary) — not `CanonicalEntityFooter`, whose overflow always offers Archive/Trash.
- [useAccountStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/account-station/useAccountStation.ts) takes no record input (there is only ever one Account); it fetches, holds busy/error state, and exposes every mutation. Save only writes the draft; Publish settles and activates in the same backend call, so there is no separate user-facing Settle control.
- [api.ts](../../wp-content/plugins/qsd-platform/resources/ts/account-station/api.ts) maps the wire snake_case projection to camelCase and is the one place that bypasses the shared JSON `apiClient` for the media upload (`FormData`, since the backend reads a real `$_FILES` upload).

## Validation

From the plugin root: `npm test` (includes `tests/account-station.php`, `tests/account-brand.php`, `tests/platform-identifier-station.php`, and `npm run contract:account-registration`, which proves the nav/destination/binding/drawer resolve, Service is unaffected, and no archive/trash/restore/delete concept exists anywhere under `account-station/`) and `npm run docs:check`.

## Related Code Maps

[Platform Identifier Station](platform-identifier-station.md), [Station Manager](station-manager.md), [Settings and Security](settings-station.md) (Phase D relocates its presentation here; its backend stays put).
