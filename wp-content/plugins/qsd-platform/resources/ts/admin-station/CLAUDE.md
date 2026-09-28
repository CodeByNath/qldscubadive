# Admin Station — Host Shell

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`resources/ts/admin-station/` is the thin host shell. It renders the station frame and one drawer layer, and resolves everything else from the Station Manager registries. It owns no entity data, no mutations and no domain rules.

- `AdminStation.tsx`, `AdminStationContext.tsx` — root boundary, theme, active destination.
- `shell/AdminStationLayout|Header|Body|Footer|SlideMenu|Dropdown.tsx` — the station frame. The header wordmark is the brand ("Queensland Scuba Diving").
- `shell/drawer/AdminStationDrawer.tsx` — **the one drawer host**: layer, backdrop, panel, size modifier, header (title, one optional entity-supplied header action beside Close ×, via `setHeaderAction`), scrolling body, footer band, scroll lock, Escape, focus restore, close guard, mode clamping, unresolved-key fallback. It never branches on entity type. `setHeaderAction`, like `setHeaderHidden`, resets to empty on every content-identity change.
- `shell/drawer/AdminStationDrawerContext.tsx` — one open drawer: template key, opaque record id, mode, originating-wall refetch.
- `shell/icons.tsx` — the Admin-owned icon set peers may consume.
- `home/`, `presentation/` — the home shell and the station-level presentation primitives (status pill, metric block, split action, card grid, tab set).
- `presentation/StationTabSet.tsx` — **the one tab primitive** for lanes inside a wall. It imports only Preact and names no station, entity, drawer route, data source, or lane.
- `register.ts` — Admin Station's Category source, kits, and Category drawer, plus `registerPresentationPolicy()`, the string-key placement policy for every Station.
- `stations/serviceCategory/` — the Category drawer host adapter and card source.
- `theme/useStationTheme.ts` — light/dark selection written to `data-station-theme`; persisted per browser.
- `styles/` — see the ownership boundary below.

The backend host is `src/Modules/AdminStation/` (the `/station/` route, login gate, access-denied state) and `src/Core/AssetLoader.php`.

## Boundaries

Do not add a second drawer host, a second drawer registry, or a second field system. Drawer entry behaviour — how a drawer opens, which mode it opens in, and which size it takes — is fixed by the registration contract in `@/station-manager`.

Domain Stations legitimately import `admin-station/presentation/` and `admin-station/shell/icons`. Nothing here may import a station's mutation hooks.

## Style ownership

`styles/admin-station-tokens.css` is the single token definition site for the Admin Station. Components reference tokens; they do not hard-code colour or shape.

Every Admin-only class name is `cz-station-*` (`cz-station-iconbtn`, `cz-station-drawer__close`, `cz-station-drawer-iconbtn`, …) — never a bare `cz-*` name. The `cz-*` prefix is shared with `atomic-engine/css/` (its own token family, `--cz-color-*`), which loads on the `/station/` route too. A bare `cz-icon-btn` class once collided with an identically-named Atomic button class and silently inherited its styling — always grep `atomic-engine/css/` before naming a new class here.

The shell sheet owns station layout, header, navigation, body, footer, slide menu, presentation surfaces, station tabs, the drawer layer, backdrop, drawer placement, drawer widths and station breakpoints.

It does **not** own control appearance. Input, select, textarea, checkbox, label, hint, error, focus, disabled, readonly and field sizing belong to the drawer kit's field system (`cz-tf-*`). Feature CSS living in this sheet owns grids, rows, columns and domain-specific presentation only, and must not declare `border`, `border-radius`, `height`, `min-height`, `outline`, `box-shadow`, `background` or `color` on an `input`, `select`, `textarea`, `label` or a `cz-tf-*` class.

Read [Admin Station](../../../../../../docs/code-map/admin-station.md), [Admin Station Styles](../../../../../../docs/code-map/admin-station-styles.md), [Admin Station Drawer](../../../../../../docs/code-map/admin-station-drawer.md), and the locked [Admin Station Field System](../../../../../../docs/architecture/admin-station-field-system-v1.md).

## Validation

From the plugin root: `npm test` (typecheck, build, `contract:admin-station-css`, `contract:station-tabset`, `contract:supported-action-footer`, …) and `npm run docs:check`.
