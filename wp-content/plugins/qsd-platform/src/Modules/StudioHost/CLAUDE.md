# Studio Host Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

This module owns the QSD-side host for the independently built WEX Admin Dashboard at the fixed `/studio/` route — the route, document, asset delivery, and private storage only. It owns no WEX markup, presentation, identity semantics, or QSD business data.

- `StudioHostModule.php` — the `/studio/` rewrite rule, document template, noindex/no-toolbar marking, and access gate. Reuses `Core\PlatformAccess::CAP` (the same capability `/station/` gates on); a visitor who needs to sign in is pointed at `AdminStationModule::url()` — consumed only as a public URL, never that module's templates, CSS, or UI.
- `StudioAssetGateway.php` — enqueues a WEX-supplied bundle from `studio-assets/manifest.json`, only on `/studio/` for a signed-in platform manager; every manifest path is `realpath()`-contained to that directory with a directory-separator boundary check (not a bare string prefix), so a sibling directory, a `../` traversal, or a symlink pointing outside it all resolve to nothing. With no manifest present, the route still renders with nothing enqueued.
- `StudioStorage.php` — the host-private writable root for WEX's identity-space storage, one directory above the WordPress install itself (`dirname(ABSPATH)`) — outside any web server's document root regardless of server software, never under `wp_upload_dir()`. `.htaccess`/`index.php` are still written as defence in depth; a failed write to either fails the whole initialization closed. First initialization is idempotent and fail-closed, and runs only from the explicit REST action below — never automatically.
- `Http/StudioStorageController.php` — the minimum authorized handshake: storage init status/action, and readback/write of one identity-space JSON blob. Every route requires `PlatformAccess::CAP`.
- `Core\Plugin` wires `StudioHostModule`, `StudioAssetGateway`, and `StudioStorageController` independently of `AdminStationModule`/`AssetLoader`.

## Boundaries

Never modify `AdminStationModule.php` or `AssetLoader.php` from here, and never reference their templates, CSS, or `window.QSDConfig` — Studio has its own document, its own `window.QSDStudioConfig`, and its own asset directory (`studio-assets/`, distinct from `dist/`). Never mint identity through `PlatformIdentifierStation`; WEX's own UI identity and QSD business identity stay separate. Never widen `StudioStorageController` into a generic path-addressable file API or a business-data route — it exposes exactly one bounded identity resource. Never auto-run `StudioStorage::initialize()` outside the explicit `POST /qsd/v1/studio/storage/init` action.

Read [Studio Host](../../../../../../docs/code-map/studio-host.md).

## Validation

From the plugin root: `npm test` (includes `tests/studio-host.php`, `tests/studio-asset-gateway.php`, `tests/studio-storage.php`, `tests/studio-storage-unwritable-root.php`, `tests/studio-storage-rest.php`, and `tests/studio-storage-rest-uninitialized.php`) and `npm run docs:check`.
