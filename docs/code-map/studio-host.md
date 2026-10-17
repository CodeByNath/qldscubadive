# Studio Host

Studio Host is the QSD-owned backend for a second, independently built frontend — the WEX Admin Dashboard — served at the fixed `/studio/` route, coexisting with `/station/` without changing it. It owns no WEX markup, presentation, or identity semantics.

Backend root: `wp-content/plugins/qsd-platform/src/Modules/StudioHost/`

## Ownership

- `StudioHostModule.php` — the `/studio/` rewrite rule (query-var fallback `?qsd_studio=1`), its own document template, noindex/no-toolbar marking, and the access gate. Reuses the existing `Core\PlatformAccess::CAP` capability `/station/` already gates on. A signed-out or uncapable visitor sees a bare access-denied state linking to `AdminStationModule::url()` (a public URL only, never its templates/CSS/UI). `StudioHostModule::url()` is the one canonical address.
- `StudioReleaseLock.php` — the QSD-owned pin (release id, manifest URL, expected manifest SHA-384 digest, `contract`) for one approved, immutable WEX release, normally committed from a reviewed WEX closeout record. With no explicit pin (production), the constructor resolves it once through `StudioStagingPointer`, falling back to its own four constants whenever that collaborator has nothing newer. `verifiedRelease()` fetches the resolved manifest URL over HTTPS and fails closed on a non-200/network response, a digest mismatch, an unexpected `contract`/`releaseId`, a missing/malformed `files` inventory, or an `entry`/`styles` path absent from it, after every key passes `StudioAssetPath::isUrlSafeRelativePath()`. A verified result is transient-cached only after full verification; switching the pin is never an asset copy — `tests/studio-release-lock.php`.
- `StudioStagingPointer.php` — the one allowlisted fetch of WEX's `staging-pointer.json`, consulted only when `wp_get_environment_type() === 'staging'` (elsewhere, or if unset, a no-op). Validates the exact key set, `schema`/`contract`, an integer `sequence`, an allowlisted `change`, a `releaseId` matching WEX's naming shape, a `manifestPath` both URL-safe and *derived* from it, and a well-formed `manifestDigest`; failure returns the static fallback. A durable `get_option` record of the highest accepted `sequence` (never just a transient) stops a replay/downgrade across a restart; a higher sequence may still re-name an earlier release (rollback). Bounded to one revalidation per 300s — `tests/studio-staging-pointer.php`.
- `StudioAssetGateway.php` — enqueues only what `StudioReleaseLock` verified, only on `/studio/` for a signed-in platform manager, adding `integrity`/`crossorigin="anonymous"` to each tag it enqueued from that release. The WEX bundle is never copied into this repository; until the lock verifies a release, nothing is enqueued. Writes `window.QSDStudioConfig` and `globalThis.wexStudioHostOptions` — `{ logoImageBinding: { source: 'profile.logo', target: 'imageUrl' }, sourceValues: { 'profile.logo': <url|null> } }` — via `AccountRepository`/`AccountMedia`; `null` when unpublished.
- `StudioStorage.php` — the host-private writable root for WEX's identity-space storage. Never inferred from `ABSPATH`; requires an explicit `QSD_STUDIO_PRIVATE_ROOT` constant set after confirming that path is outside the document root — `root()` returns `''` and every operation fails closed until defined. `.htaccess`/`index.php` are defence in depth. Initialization is idempotent, invoked only by the explicit REST action below. Holds one size-capped, JSON-validated `identity.json` blob, not a generic file API — see Deployment-time verification below.
- `Http/StudioStorageController.php` — the minimum authorized host handshake: init status/action, readback/write of the one identity blob. Every route requires `PlatformAccess::CAP`.
- `app/modules/studio-host/templates/` — `studio-document.php` (theme-independent document), `access-denied.php` (bare, no Atomic Engine CSS/drawer kit), `studio-mount.php` (mount point div only). None imports an Admin Station template.
- `studio-assets/` — retired plugin-local delivery location (Phase 1A–1G manual copy). No longer read at runtime; kept only as a git-history rollback reference, never a live fallback.

## Resource organisation

Three separate locations, none new: GitHub (`WEXdesigns`) publishes each immutable release, fetched (never copied) by `StudioReleaseLock`; QSD's media/uploads stays the sole owner of business media; `QSD_STUDIO_PRIVATE_ROOT` is the one private WEX identity root, separate from both.

## Boundaries

Studio Host never touches `/station/`'s rewrite rule, document, `AssetLoader`, or `window.QSDConfig`, and never mints identity through `PlatformIdentifierStation`. No generic business-data API: the one REST resource is a bounded identity blob, not an arbitrary read/write-by-path adapter. Frontend code is WEX's own build, loaded only from the pinned origin `StudioReleaseLock` verified; WEX owns all markup/presentation/tokens. `wexStudioHostOptions` stays one read-only logo URL. The release pin is never a browser-controlled selector, a generic API, or a mutable `main`/`latest` alias — on staging it advances only via `StudioStagingPointer`'s validated fetch; production always resolves to the fixed constants.

## Deployment-time verification

`QSD_STUDIO_PRIVATE_ROOT` must be set and confirmed non-public by requesting its would-be URL live and getting a 403/404/connection failure, not file content.

## Related Code Maps

[Admin Station](admin-station.md) (sibling host, consumed only via its public `url()`), [Platform Identifier Station](platform-identifier-station.md) (untouched by this subsystem).
