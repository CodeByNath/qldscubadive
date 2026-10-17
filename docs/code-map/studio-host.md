# Studio Host

Studio Host is the QSD-owned backend for a second, independently built frontend — the WEX Admin Dashboard — served at the fixed `/studio/` route, alongside `/station/` without changing it. It owns no WEX markup, presentation, or identity semantics.

Backend root: `wp-content/plugins/qsd-platform/src/Modules/StudioHost/`

## Ownership

- `StudioHostModule.php` — the `/studio/` rewrite rule (query-var fallback `?qsd_studio=1`), its own document template, noindex/no-toolbar marking, and the access gate, reusing `Core\PlatformAccess::CAP`. A signed-out or uncapable visitor sees a bare access-denied state linking to `AdminStationModule::url()` (public URL only, never its templates/CSS/UI). `StudioHostModule::url()` is the one canonical address.
- `StudioReleaseLock.php` — the QSD-owned pin (release id, manifest URL, expected manifest SHA-384 digest, `contract`) for one approved, immutable WEX release, normally committed from a reviewed WEX closeout record. With no explicit pin (production), the constructor resolves it once through `StudioStagingPointer`, falling back to its own four constants whenever that collaborator has nothing newer. `verifiedRelease()` delegates to `StudioManifestVerifier::verify()`; a verified result is transient-cached only after full verification, and switching the pin is never an asset copy — `tests/studio-release-lock.php`.
- `StudioManifestVerifier.php` — the fetch-and-verify routine shared by `StudioReleaseLock` and `StudioStagingPointer`: https/host check, non-200/network, digest mismatch, unexpected `contract`/`releaseId`, a malformed/missing `files` inventory, or a missing `entry`/`styles` path all fail closed to `null` — holding a pointer-driven candidate to the fixed pin's own bar.
- `StudioStagingPointer.php` — the allowlisted fetch of WEX's `staging-pointer.json`, consulted only when `wp_get_environment_type() === 'staging'` (elsewhere a no-op). Validates the exact key set, `schema`/`contract`, an integer `sequence`, an allowlisted `change`, a naming-shape `releaseId`, a *derived* `manifestPath`, a well-formed `manifestDigest`, and — per WEX ADR 0025 — a `previous` that is `null` only at `sequence` 1, otherwise an object of `sequence`/`releaseId`/`manifestDigest`. A candidate above the stored sequence is adopted only when `previous` exactly chains to the stored sequence/releaseId/digest (an unprovable gap, forged or merely skipped, fails closed instead of being bridged) and its manifest also passes `StudioManifestVerifier`; an equal sequence naming a different release, or an unverifiable manifest, is likewise rejected. The durable record is fully re-validated on every read, not merely type-checked, including a `manifestUrl` derived from its own `releaseId`. A correctly chained higher sequence may re-name an earlier release (rollback); bounded to one revalidation per 300s — `tests/studio-staging-pointer.php`.
- `StudioAssetGateway.php` — enqueues only what `StudioReleaseLock` verified, only on `/studio/` for a signed-in platform manager, adding `integrity`/`crossorigin="anonymous"` to each enqueued tag. The WEX bundle is never copied into this repository; until the lock verifies a release, nothing is enqueued. Writes `window.QSDStudioConfig` and `globalThis.wexStudioHostOptions` (`profile.logo` → `imageUrl`, via `AccountRepository`/`AccountMedia`; `null` when unpublished).
- `StudioStorage.php` — the host-private writable root for WEX's identity-space storage. Never inferred from `ABSPATH`; requires an explicit `QSD_STUDIO_PRIVATE_ROOT` constant confirmed outside the document root — `root()` returns `''` and every operation fails closed until defined. `.htaccess`/`index.php` are defence in depth; initialization is idempotent, invoked only by the explicit REST action below. Holds one size-capped, JSON-validated `identity.json` blob, not a generic file API.
- `Http/StudioStorageController.php` — init status/action, readback/write of the one identity blob, every route gated on `PlatformAccess::CAP`.
- `app/modules/studio-host/templates/` — `studio-document.php` (theme-independent), `access-denied.php` (bare, no Atomic Engine CSS/drawer kit), `studio-mount.php` (mount div only). None imports an Admin Station template.
- `studio-assets/` — retired plugin-local delivery location. No longer read at runtime; kept only as a git-history rollback reference, never a live fallback.

## Resource organisation

Three separate locations, none new: GitHub (`WEXdesigns`) publishes each immutable release, fetched (never copied) by `StudioReleaseLock`; QSD media/uploads owns business media; `QSD_STUDIO_PRIVATE_ROOT` is the one private WEX identity root, separate from both.

## Boundaries

Studio Host never touches `/station/`'s rewrite rule, document, `AssetLoader`, or `window.QSDConfig`, and never mints identity through `PlatformIdentifierStation`. No generic business-data API: the one REST resource is a bounded identity blob. Frontend code is WEX's own build, loaded only from the pinned origin `StudioReleaseLock` verified; WEX owns all markup/presentation/tokens. `wexStudioHostOptions` stays one read-only logo URL. The release pin is never browser-controlled or a mutable `main`/`latest` alias — on staging it advances only via `StudioStagingPointer`'s validated, chained fetch; production always resolves to the fixed constants.

## Deployment-time verification

`QSD_STUDIO_PRIVATE_ROOT` must be confirmed non-public by requesting its would-be URL live and getting a 403/404/connection failure, not file content.

## Related Code Maps

[Admin Station](admin-station.md) (sibling host, consumed only via `url()`), [Platform Identifier Station](platform-identifier-station.md) (untouched here).
