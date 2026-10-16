# Studio Host

Studio Host is the QSD-owned backend for a second, independently built frontend — the WEX Admin Dashboard — served at the fixed `/studio/` route, coexisting with `/station/` without changing it: separate rewrite rule, document, asset delivery, and private storage. It owns no WEX markup, presentation, or identity semantics.

Backend root: `wp-content/plugins/qsd-platform/src/Modules/StudioHost/`

## Ownership

- `StudioHostModule.php` — the `/studio/` rewrite rule (query-var fallback `?qsd_studio=1`), its own document template, noindex/no-toolbar marking, and the access gate. Reuses the existing `Core\PlatformAccess::CAP` capability `/station/` already gates on — no second authorization scheme. A visitor who is not signed in, or lacks the capability, sees a bare access-denied state that links to `AdminStationModule::url()` (consumed only as a public URL helper, never its templates/CSS/UI) rather than a duplicated login form. `StudioHostModule::url()` is the one canonical address.
- `StudioReleaseLock.php` — the QSD-owned pin (release id, manifest URL, expected manifest SHA-384 digest, `contract`) for one approved, immutable WEX release, committed from a reviewed WEX closeout record, never request/runtime input. `verifiedRelease()` fetches that pinned URL over HTTPS and fails closed on a non-200/network response, a digest mismatch, an unexpected `contract`/`releaseId`, a missing/malformed `files` inventory, or an `entry`/`styles` path absent from it. Inventory keys pass `StudioAssetPath::isUrlSafeRelativePath()` before being appended to the base URL. A verified result is transient-cached only after full verification. Moving the pin to a retained or new release is a constant edit, never an asset copy — see `tests/studio-release-lock.php`.
- `StudioAssetGateway.php` — enqueues only what `StudioReleaseLock` verified, only on `/studio/` for a signed-in platform manager, adding `integrity`/`crossorigin="anonymous"` to each `<script>`/`<link>` tag it enqueued from that release. The WEX bundle is never copied into this repository; until the lock verifies a release, nothing is enqueued — never a fallback to an unverified asset. Writes `window.QSDStudioConfig` and `globalThis.wexStudioHostOptions` — `{ logoImageBinding: { source: 'profile.logo', target: 'imageUrl' }, sourceValues: { 'profile.logo': <url|null> } }`, via `AccountRepository`/`AccountMedia`; `null` when unpublished.
- `StudioStorage.php` — the host-private writable root for WEX's identity-space storage. Its location is never inferred from `ABSPATH`'s position, which can't safely be assumed non-public. It requires an explicit `QSD_STUDIO_PRIVATE_ROOT` constant the administrator sets in `wp-config.php` after confirming that path is genuinely outside their document root; `root()` returns `''` and every operation fails closed until it's defined. `.htaccess`/`index.php` are written as defence in depth; a failed write fails initialization closed. First initialization is idempotent, invoked only by the explicit REST action below, never automatically. Holds exactly one size-capped, JSON-validated `identity.json` blob, not a generic file API. Direct HTTP denial against the configured path is real-server proof only — see Deployment-time verification below.
- `Http/StudioStorageController.php` — the minimum authorized host handshake: `GET /qsd/v1/studio/storage` (init status), `POST /qsd/v1/studio/storage/init` (the explicit initialization action), `GET`/`PUT /qsd/v1/studio/storage/identity` (readback/write of the one identity blob). Every route's `permission_callback` requires `PlatformAccess::CAP`; no business-data route is exposed.
- `app/modules/studio-host/templates/` — `studio-document.php` (the theme-independent document), `access-denied.php` (bare, no Atomic Engine CSS, no drawer kit), `studio-mount.php` (the mount point div only). None imports an Admin Station template.
- `studio-assets/` — retired plugin-local delivery location (Phase 1A–1G manual copy). No longer read at runtime; kept only as this mechanism's git-history rollback reference, never a live fallback.

## Resource organisation

Three separate locations, none new: GitHub (`WEXdesigns`) publishes each immutable release, fetched (never copied) by `StudioReleaseLock`; QSD's media/uploads stays the sole owner of business media, untouched here; `QSD_STUDIO_PRIVATE_ROOT` is the one private WEX identity root, separate from both.

## Boundaries

Studio Host never touches `/station/`'s rewrite rule, document, `AssetLoader`, or `window.QSDConfig`, and never mints identity through `PlatformIdentifierStation`. It exposes no generic business-data API: the one REST resource is a bounded identity-space blob, not an arbitrary read/write-by-path adapter. Frontend code is WEX's own release build, loaded only from the pinned origin `StudioReleaseLock` verified; WEX owns all markup, presentation, and design tokens. `wexStudioHostOptions` stays one read-only logo URL. The release pin is a QSD-committed constant, never a browser-controlled selector or a mutable `main`/`latest` alias.

## Deployment-time verification

`QSD_STUDIO_PRIVATE_ROOT` must be set before deployment and confirmed non-public by requesting its would-be URL live and getting a 403/404/connection failure, not file content.

## Related Code Maps

[Admin Station](admin-station.md) (the sibling host `/studio/` coexists with, consumed only via its public `url()`), [Platform Identifier Station](platform-identifier-station.md) (deliberately untouched by this subsystem).
