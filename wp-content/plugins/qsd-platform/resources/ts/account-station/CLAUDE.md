# Account Station — Frontend Peer

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

Account is a singleton hierarchy, not a repeatable list entity: exactly one Account (and its Settings/Tools/Profile nodes) ever exists per site. Its Publish/Disable/Enable are computed by the shared `StationLifecycle` engine itself (`AccountBrand.php` calls `statusRouteTransition`/`isLive` directly, the same calls Service's own `/status` route makes), restricted to the `active`/`disabled` subset of that vocabulary — it mints no `draft`/`archived`/`trashed` record and exposes no Archive/Trash/Restore/Delete action anywhere, not in the backend routes, the footer, the drawer schema, or the registered action intents. Whether that should instead become full travel-table conformance is an open exception proposal awaiting an explicit Reviewer/Owner decision, not a settled exemption. `npm run contract:account-registration` enforces the current state at the source level.

## Ownership and entry points

`resources/ts/account-station/` is the top-level Account peer's data, surface, presentation, and drawer boundary.

- `types.ts` — zero-import Account contracts.
- `api.ts` — the one implementation of every Account-owned endpoint call. The one call that bypasses the shared JSON `apiClient`: media upload sends `FormData`, since the backend reads a real `$_FILES` upload, not a JSON body.
- `derive.ts` — stateless pill-status and publish-readiness projections.
- `useAccountStation.ts` — detail fetch, busy/error state, and every mutation (bootstrap, saveBrand, publish, disable, enable, uploadMedia). Takes no record input — there is only ever one Account, so there is no `null`/`'new'` sentinel to represent.
- `surface/useAccountCard.ts`, `presentation/AccountCard.tsx` — the Home card: one `ReadBlock`, no grid, no pagination. A separate read from the drawer's own fetch, the same two-instance rule Service keeps so refreshing one cannot disturb the other.
- `presentation/AccountSettingsSection.tsx` — the second, plain presentation section beneath the Brand card (no lane/tab wrapper — Account has only these two things). Renders the shared `StationSettings` pattern for `stationId="account"`. The `settings.rezdy-importer`/`settings.api-keys` contributions' `stationIds` name `account` only — the actual relocation, not an additive second path: Services' own Settings tab no longer presents these panels. Never contributes `General`/Service fields — that stays Services-only.
- `surface/AccountDrawerHost.tsx` — implements `DrawerContent` directly. Account's one module (Brand) needs no generic multi-module `EntityDrawer`/schema composition built for Service's three-module structure. Read mode renders a `ReadBlock`; Edit mode renders `InlineEditorShell` over `drawer/AccountBrandEditor.tsx`. The footer is a direct `EntityActionFooter` composition (Disable/Enable split, empty overflow, Publish primary) — never `CanonicalEntityFooter`, since its non-bin branch always places Archive (and, unless new, Trash) in its overflow with no suppression option; giving it one is a shared-component change affecting Category and Package Family too, not something to improvise here unilaterally.
- `drawer/AccountBrandEditor.tsx` — Name, Code (`AdminField`), and two media pickers. File upload is not one of the eight `AdminFieldType`s (none exists in Admin Station), so each picker is its own small dedicated control, not a field definition.
- `register.ts` — registers Account's nav item, destination, data source, template kit, and drawer template with Station Manager. Entry-only; imported only by `resources/ts/modules/admin-station.ts`. It does **not** author placement — `admin-station/register.ts`'s `registerPresentationPolicy()` does, exactly as it does for Service.

## Boundaries

No file here imports a Service peer, and nothing here authors `registerSurfaceBindings`/`registerPresentationPolicy` — Admin decides placement. Presentation (`presentation/`, the read side of `surface/`) must not call `./api` directly outside the registered data-source hooks. There is no Archive/Trash/Restore/Delete action, dialog, or schema entry anywhere in this directory; if one ever becomes genuinely necessary, it needs a separate, explicit Owner decision recorded in the active work file before any code change, not a frontend-only addition.

Read [Account Station](../../../../../../docs/code-map/account-station.md) and [Station and Drawer Lifecycle Contract v1](../../../../../../docs/architecture/StationDrawerLifecycleContract-v1.md).

## Validation

From the plugin root: `npm test` (includes `npm run contract:account-registration`) and `npm run docs:check`.
