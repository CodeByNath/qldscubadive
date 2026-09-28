# Admin Station

Admin Station is the presentation and control Station served at the fixed `/station/` route. It owns the visible administration shell, presentation tools, and display policy. It is also the thin host through which Station Manager composes capabilities registered by domain Stations; hosting does not transfer domain or persistence authority.

Frontend root: `wp-content/plugins/qsd-platform/resources/ts/admin-station/`

## Ownership

- `presentation/` owns the station presentation shell, generic card-grid kit, Service Category carousel, status pill, metric, split-action, and tab-set components. Peer imports of these modules are legal consumption of Admin presentation capabilities. `StationTabSet.tsx` is the one tab primitive for lanes inside a wall; see [Station Tab Set](station-tab-set.md).
- `shell/` owns layout and navigation chrome, icons, local controls, and the single entity-agnostic drawer shell.
- `register.ts` registers Admin's Service Category source, presentation kits, and the Category drawer. Its separate `registerPresentationPolicy()` declares all current surface bindings and the default home by string key.
- `stations/serviceCategory/` hosts the Category drawer adapter and card source.
- `theme/`, `home/`, and `styles/` remain presentation concerns. `theme/useStationTheme.ts` switches light/dark through `data-station-theme`; every colour is a token in `styles/admin-station-tokens.css`.

Admin Station does not own Service data, validation, lifecycle, saves, or drawer compositions.

## Boot and runtime

`resources/ts/modules/admin-station.ts` is the only importer of peer `register.ts` files. It imports styles, calls Service and Admin registration, applies Admin presentation policy, finalizes Station Manager, and then registers the Preact app with the runtime mount registry against the `qsd-admin-station` element.

```text
registered navigation → resolved destination → active station
  → Admin presentation shell → Station Manager surface host
  → owning Station source + registered presentation kit
  → native-record intent → Admin drawer shell
  → owning Station drawer contract and save authority
```

`AdminStationContext.tsx` holds only theme and selected destination state. `shell/AdminStationBody.tsx` selects the resolved station, falling back to Station Manager's default, and renders one `StationPresentationShell`. Successful drawer mutations refresh only the surface that opened the drawer.

## Backend and assets

- `src/Modules/AdminStation/AdminStationModule.php` owns the `/station/` rewrite rule (query-var fallback `?qsd_station=1` without pretty permalinks), flushes it once per `REWRITE_VERSION`, serves the plugin's own document template, and marks the route noindex, no-cache, and toolbar-free. `AdminStationModule::url()` is the one canonical address.
- `app/modules/admin-station/templates/station-document.php` is the theme-independent document. It renders `login-gate.php` for a logged-out visitor, `access-denied.php` for a user without `PlatformAccess::CAP`, or `admin-station.php` (the mount point) — never WP admin.
- `src/Modules/AdminStation/AdminStationAuth.php` processes the login gate's POST on `template_redirect`, only on the Admin Station route, and redirects to `AdminStationModule::url()` — never a client-supplied destination. WordPress remains the auth/session host; accounts and roles are created in WordPress (`Core\PlatformAccess` registers the role and capability only).
- `src/Core/AssetLoader.php` loads nothing outside `/station/`. There it enqueues the Atomic Engine CSS, `dist/css/drawer-kit.css`, and `dist/css/admin-station.css`; for a signed-in platform manager it also writes `window.QSDConfig` (API root, REST nonce, logout URL back to the Station) and loads `dist/js/admin-station.js` as a module.
- `vite.config.ts` emits the Admin Station JavaScript and CSS bundles.

## Related Code Maps

[Station Manager](station-manager.md), [Navigation](admin-station-navigation.md), [Surface Binding](admin-station-surface-binding.md), [Home Shell](admin-station-home-shell.md), [Drawer](admin-station-drawer.md), [Cards](admin-station-cards.md), and [Styles](admin-station-styles.md).
