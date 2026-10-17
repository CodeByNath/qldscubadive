# Studio Host

Studio Host is the QSD-owned backend for a second, independently built frontend — the WEX Admin Dashboard — served at the fixed `/studio/` route, alongside `/station/` unchanged. It owns no WEX markup, presentation, or identity semantics.

Backend root: `wp-content/plugins/qsd-platform/src/Modules/StudioHost/`

## Ownership

- `StudioHostModule.php` — the `/studio/` rewrite rule (query-var fallback `?qsd_studio=1`), document template, noindex/no-toolbar marking, and access gate, reusing `Core\PlatformAccess::CAP`. A signed-out/uncapable visitor sees a bare access-denied state linking to `AdminStationModule::url()` (public URL only).
- `StudioReleaseLock.php` — the QSD-owned pin (release id, manifest URL, expected SHA-384 digest, `contract`) for one approved, immutable WEX release, committed from a reviewed WEX closeout record. With no explicit pin (production), the constructor resolves it once through `StudioStagingPointer`, falling back to `staticFallback()` whenever that collaborator has nothing newer. `verifiedRelease()` delegates to `StudioManifestVerifier::verify()`, cached only after full verification; switching the pin is never an asset copy — `tests/studio-release-lock.php`.
- `StudioManifestVerifier.php` — the fetch-and-verify routine shared by `StudioReleaseLock` and `StudioStagingPointer`: https/host check, non-200/network, digest mismatch, unexpected `contract`/`releaseId`, malformed/missing `files` inventory, or a missing `entry`/`styles` path all fail closed to `null`.
- `StudioStagingPointer.php` — the allowlisted fetch of WEX's `staging-pointer.json`, consulted only when the staging gate is active (elsewhere a no-op): `wp_get_environment_type() === 'staging'` OR a CI-written deployment-proof marker (below) — never a browser URL/Host header. Validates the exact key set, `schema`/`contract`, an integer `sequence`, an allowlisted `change`, a naming-shape `releaseId`, a *derived* `manifestPath`, a well-formed `manifestDigest`, and — per WEX ADR 0025 — a `previous` that is `null` only at `sequence` 1, otherwise an object of `sequence`/`releaseId`/`manifestDigest`. A candidate above the stored sequence is adopted only when `previous` exactly chains to the stored sequence/releaseId/digest (an unprovable gap fails closed rather than being bridged) and its manifest also passes `StudioManifestVerifier`; an equal sequence naming a different release, or an unverifiable manifest, is likewise rejected. The durable record is fully re-validated on every read, including a derived `manifestUrl`. A correctly chained higher sequence may roll back; bounded to one revalidation per 300s. `describeGate()`/`storedSequence()` expose this state for diagnostics only — `tests/studio-staging-pointer.php`.
- `StudioStagingDiagnostic.php` — one non-secret report (environment type, deployment-proof status, gate state, stored sequence, release id, manifest-verified, fallback reason), run over SSH via `app/cli/studio-staging-diagnostic.php` by `deploy-staging` — real-server proof, no browser/wp-admin needed — `tests/studio-staging-diagnostic.php`.
- `StudioAssetGateway.php` — enqueues only what `StudioReleaseLock` verified, only on `/studio/` for a signed-in platform manager, adding `integrity`/`crossorigin="anonymous"` to each tag. Never copies the WEX bundle into this repo; nothing is enqueued until the lock verifies a release. Writes `window.QSDStudioConfig` and `globalThis.wexStudioHostOptions` (`profile.logo` → `imageUrl`, via `AccountRepository`/`AccountMedia`; `null` when unpublished).
- `StudioStorage.php` — the host-private writable root for WEX's identity storage. Never inferred from `ABSPATH`; requires an explicit `QSD_STUDIO_PRIVATE_ROOT` constant confirmed outside the document root — `root()` returns `''` and every operation fails closed until defined. `.htaccess`/`index.php` are defence in depth. Holds one size-capped, JSON-validated `identity.json` blob, not a generic file API.
- `Http/StudioStorageController.php` — init status/action, readback/write of the one identity blob, every route gated on `PlatformAccess::CAP`.
- `app/modules/studio-host/templates/` — `studio-document.php` (theme-independent), `access-denied.php` (bare), `studio-mount.php` (mount div only). None imports an Admin Station template.
- `studio-assets/` — retired plugin-local delivery location; git-history rollback reference only, never a live fallback.

## Resource organisation

Three locations, none new: GitHub (`WEXdesigns`) publishes each immutable release, fetched (never copied) by `StudioReleaseLock`; QSD media/uploads owns business media; `QSD_STUDIO_PRIVATE_ROOT` is the one private WEX identity root, separate from both.

## Boundaries

Studio Host never touches `/station/`'s rewrite rule, document, `AssetLoader`, or `window.QSDConfig`, and never mints identity through `PlatformIdentifierStation`. No generic business-data API: the one REST resource is a bounded identity blob. Frontend code is WEX's own build, loaded only from the pinned origin `StudioReleaseLock` verified; WEX owns all markup/presentation/tokens. `wexStudioHostOptions` stays one read-only logo URL. The release pin is never browser-controlled or a mutable `main`/`latest` alias — on staging it advances only via `StudioStagingPointer`'s validated, chained fetch; production always resolves to the fixed constants.

## Deployment-time verification

`QSD_STUDIO_PRIVATE_ROOT` must be confirmed non-public by requesting its would-be URL live and getting a 403/404/connection failure, not file content. `deploy-staging` also writes a deployment-proof marker (`qsd-studio-staging-deploy.json`, under the server's `wp-content`, never committed here) and runs the diagnostic above over SSH, printing its report to the Action log.

## Related Code Maps

[Admin Station](admin-station.md) (sibling host, consumed only via `url()`), [Platform Identifier Station](platform-identifier-station.md) (untouched here).
