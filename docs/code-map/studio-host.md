# Studio Host

Studio Host is the QSD-owned backend for a second, independently built frontend — the WEX Admin Dashboard — served at the fixed `/studio/` route, coexisting with `/station/` without changing it: separate rewrite rule, document, asset delivery, and private storage. It owns no WEX markup, presentation, identity semantics, or QSD business data.

Backend root: `wp-content/plugins/qsd-platform/src/Modules/StudioHost/`

## Ownership

- `StudioHostModule.php` — the `/studio/` rewrite rule (query-var fallback `?qsd_studio=1`), its own document template, noindex/no-toolbar marking, and the access gate. Reuses the existing `Core\PlatformAccess::CAP` capability `/station/` already gates on — no second authorization scheme. A visitor who is not signed in, or lacks the capability, sees a bare access-denied state that links to `AdminStationModule::url()` (consumed only as a public URL helper, never its templates/CSS/UI) rather than a duplicated login form. `StudioHostModule::url()` is the one canonical address.
- `StudioAssetGateway.php` — enqueues a WEX-supplied bundle only on `/studio/`, only for a signed-in platform manager, from `studio-assets/manifest.json` (`{entry, styles, version}`). Each manifest path must first pass `isUrlSafeRelativePath()` (no `..`/`.` segment, no leading slash/backslash, no drive letter, no `%`), then resolve inside `studio-assets/` via `realpath()` containment requiring an exact match or a directory-separator boundary — not a bare string prefix, which would wrongly accept a sibling like `studio-assets-evil/`. The same validated string builds both the file check and the browser URL, so traversal, sibling-prefix, symlink escape, and unsafe string forms all resolve to nothing with no gap between what was checked and what the client requests. Writes `window.QSDStudioConfig` (Studio-scoped REST root and nonce only, never the full `qsd/v1` root or `/station/`'s `window.QSDConfig`).
- `StudioStorage.php` — the host-private writable root for WEX's identity-space storage. Its location is never inferred from `ABSPATH`'s position — no code inside the install can see its own server's docroot configuration, so it can't safely assume a path above it is non-public (false for a subdirectory install or a web alias). It requires an explicit `QSD_STUDIO_PRIVATE_ROOT` constant the administrator sets in `wp-config.php` after confirming that path is genuinely outside their document root; `root()` returns `''`, and every operation fails closed, until it's defined. `.htaccess`/`index.php` remain as defence in depth, their creation checked — a failed write fails initialization closed. First initialization is idempotent, invoked only by the explicit REST action below, never automatically. Holds exactly one resource, a size-capped, JSON-validated `identity.json` blob, not a generic file API. Direct HTTP denial against the configured path is real-server proof only — see Deployment-time verification below.
- `Http/StudioStorageController.php` — the minimum authorized host handshake: `GET /qsd/v1/studio/storage` (init status), `POST /qsd/v1/studio/storage/init` (the explicit initialization action), `GET`/`PUT /qsd/v1/studio/storage/identity` (readback/write of the one identity blob). Every route's `permission_callback` requires `PlatformAccess::CAP`; no business-data route is exposed.
- `app/modules/studio-host/templates/` — `studio-document.php` (the theme-independent document), `access-denied.php` (bare, no Atomic Engine CSS, no drawer kit), `studio-mount.php` (the mount point div only). None imports an Admin Station template.
- `studio-assets/` — the versioned, publicly-fetchable delivery location WEX's own build is copied into (same trust model as `dist/js/admin-station.js`). Holds the WEX Phase 1A Dashboard shell bundle, built from `CodeByNath/WEXdesigns` `main` and copied in wholesale; later releases replace it the same way, never merge.

## Resource organisation

Three separate, non-overlapping locations, none new: GitHub (`WEXdesigns`) owns WEX's versioned source and builds the public `studio-assets/` bundle; QSD's existing media/uploads stays the sole owner of persistent business media, untouched here; `QSD_STUDIO_PRIVATE_ROOT` is the one private WEX identity root, separate from both.

## Boundaries

Studio Host never touches `/station/`'s rewrite rule, document, `AssetLoader`, or `window.QSDConfig`; those are unmodified by this subsystem. It never mints identity through `PlatformIdentifierStation` — WEX's UI identity and QSD business identity stay separate. It exposes no generic business-data API: the one REST resource is a bounded identity-space blob, not an arbitrary read/write-by-path adapter. Frontend code is WEX's own `studio-assets/` build; WEX owns all markup, presentation, and design tokens.

## Deployment-time verification

`QSD_STUDIO_PRIVATE_ROOT` must be set before deployment and confirmed non-public by requesting its would-be URL against the live server and getting a 403/404/connection failure, not file content.

## Related Code Maps

[Admin Station](admin-station.md) (the sibling host `/studio/` coexists with, consumed only via its public `url()`), [Platform Identifier Station](platform-identifier-station.md) (deliberately untouched by this subsystem).
