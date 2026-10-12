# WEX /studio host integration — queued QSD work

Status: BUILDER ACTION REQUIRED — Phase 1 bounded security correction
Actor now: Builder
Host: CodeByNath/qldscubadive
WEX coordination: CodeByNath/WEXdesigns project-work/qsd-studio-host.md, Owner-approved contract recorded at f8d2d161cd2292329b7773e573a8899334ae1c8c

## Owner-approved objective

Make the existing independently built WEX Admin Dashboard accessible as QSD /studio/ while leaving original /station/ fully functional. QSD owns its host routing, server-side authorization/session checks, private writable allocation for WEX identity-space storage, and safe independent asset delivery. WEX owns all markup, UI components, presentation/design tokens, identity semantics, approved portable bootstrap and storage adapter. No reuse of legacy QSD UI, drawers, presentation templates or CSS. No QSD business records, data plugs, navigation or Logo wiring. Owner commands each future plug individually.

## Release precondition — satisfied (2026-10-12)

Independently re-verified before release, not assumed: `project-work/account-station-transfer-batch.md` status is `ACCEPTED`. Fresh `git ls-remote --heads origin` shows exactly three permanent heads — `main` (`d913310`), `staging` (`29503ae`), `Project-work-instructions` — confirming the redundant `account-staging-fixes` topic branch was retired and capacity for one new topic branch is clear. QSD staging was NOT deployed and is not implied by this release; Account Station's own deployment gate stays exactly as that file records it, untouched by this action. Production deployment remains excluded.

Phase 1 below is released for Builder. This release does not authorize any staging/production deployment, nor does it touch Account Station.

## Phase 1 — bounded host Builder task once released

1. Read QSD AGENTS.md, docs/ai-index.md, docs/roadmap.md, docs/foundation/repository-governance.md, Admin Station and Platform ID Code Maps, Station/Drawer contract, and actual main source. Compare existing rewrite/module boot, assets, access gates and deployment boundaries. Read WEX contract as integration input, not QSD product authority.
2. Implement a separate /studio/ host route/document with QSD-approved access control, using existing WordPress session and authorization capabilities; unauthorized callers cannot retrieve protected initialization or mutate identity. Do not change /station/ or its QSD bundle.
3. Provide a versioned WEX-only asset mount/delivery seam that serves supplied WEX bundles without importing QSD visual code or depending on GitHub Pages paths.
4. Provide a host-private writable identity storage root with isolation, readback, fail-closed permission checks, and administrator-approved first initialization. Do not create WEX IDs in QSD PlatformIdentifierStation; host business identity and WEX UI identity remain separate. Storage init approval is a runtime action; do not silently preapprove it.
5. Expose only the minimum authorized host handshake required by WEX's approved bootstrap; do not invent business-data API or broad generic adapters. If contract requires new QSD ownership/architecture authority, stop for Reviewer before implementation.
6. Verify anonymous/unauthorized denial, approved access, route conflict-free coexistence with /station/, WEX asset isolation, storage integrity/restart, and regression of existing QSD endpoints. Run npm test and npm run docs:check. Record relevant browser/host tests; staging deployment requires separate authorization.

## Handoff

One governed QSD topic branch only after Reviewer releases the phase. Push candidate and verify exact remote SHA; update this same work file to AWAITING REVIEWER REVIEW with diff, test and host evidence. Stop; Reviewer then releases WEX Builder to complete WEX-side integration. Do not claim live /studio/ is deployed or validated merely from tests.

## Builder Phase 1 handoff (2026-10-12)

**Preconditions checked before branching:** `main`=`staging`=`d913310f19662d1245de342a09217d1979cebd80` (re-fetched fresh); remote heads were exactly the governed baseline of three — capacity for one topic branch. Opened `wex-studio-host` from `main` at that SHA.

**What was built, one commit (`f0825cb`):** a separate `/studio/` host route, document, and access gate (`src/Modules/StudioHost/StudioHostModule.php`), reusing the existing `Core\PlatformAccess::CAP` capability `/station/` already gates on — no second authorization scheme, and a logged-out/unauthorized visitor is pointed at `/station/`'s real sign-in (`AdminStationModule::url()`, consumed only as a public URL helper) rather than a duplicated login form. A versioned WEX-only asset delivery seam (`StudioAssetGateway.php`) reads `studio-assets/manifest.json`, `realpath()`-contains every path to that directory, writes a Studio-scoped `window.QSDStudioConfig` (never the full `qsd/v1` root, never `/station/`'s `window.QSDConfig`), and enqueues nothing — the host still renders — until WEX actually supplies a bundle. A host-private writable identity-space root (`StudioStorage.php`, under `wp_upload_dir()`, `.htaccess`/`index.php`-protected) is initialized only by an explicit `POST /qsd/v1/studio/storage/init` action (`Http/StudioStorageController.php`) — never automatically — and exposes exactly one bounded identity-blob resource (`GET`/`PUT /qsd/v1/studio/storage/identity`), not a generic path-addressable file API. No frontend/`resources/ts/` code, no WEX markup, and no QSD business data are part of this phase.

**Verified untouched, not assumed:** `git diff --stat` of `AdminStationModule.php`, `AdminStationAuth.php`, `AssetLoader.php`, and `PlatformIdentifier/` between `main` (`d913310`) and this branch is empty — zero changes to any of them. `ROUTE_SLUG`/`QUERY_VAR` are distinct constants (`studio`/`qsd_studio` vs `station`/`qsd_station`), so the two rewrite rules cannot collide.

**Test evidence at `f0825cb`:** `npm test` green in full — typecheck; every PHP test including four new files (`tests/studio-host.php`, `tests/studio-asset-gateway.php`, `tests/studio-storage.php`, `tests/studio-storage-rest.php` — route/gate behaviour, asset-gateway enqueue gating including a path-escape attempt, storage fail-closed/idempotent/restart-persistence behaviour, and REST handshake/permission behaviour, all against real temp filesystems and stubbed WordPress primitives, not mocks of the classes under test); build; JS **27/27** unchanged (no new TS contract/regression — this phase is backend-only, so none was needed); `npm run docs:check` (52 Markdown files, 21 Code Maps, new `docs/code-map/studio-host.md` and `src/Modules/StudioHost/CLAUDE.md` added, `docs/roadmap.md` updated).

**No CI run exists yet for `f0825cb`:** independently confirmed via the GitHub Actions API (`total_count: 0`) — by design, `.github/workflows/ci-deploy.yml` triggers only on push to `main`/`staging` or a pull request, never a plain topic-branch push, exactly as already established in the prior Account-fixes cycle. Local `npm test` above is the available evidence.

**Verified remote state:** `origin/wex-studio-host` HEAD = `f0825cbeb9a919821bb6c554509652f9acdff7b2` (new branch, confirmed via `git rev-parse` after push). `origin/main`/`origin/staging` unchanged at `d913310`/`29503ae`. Remote branch count is now four (governed maximum).

**Not done, per Phase 1's own bound:** no WEX markup/bundle/identity semantics (host-only, as instructed); no staging/production deployment; no browser/host runtime verification (`studio-assets/` is empty — nothing to verify live yet); no push to `main`/`staging`; no credential operation; no change to `/station/`, its bundle, or `PlatformIdentifierStation`.

**Builder next action:** none — stopping here. Reviewer: `wex-studio-host` is pushed at `f0825cb`, independently inspectable, satisfying Phase 1's six numbered requirements. Awaiting verdict before any WEX-side integration or deployment.


## Reviewer verdict and bounded correction — 12 October 2026

**Verdict: Proceed with safeguards.** Reviewer inspected pushed `wex-studio-host` at `f0825cbeb9a919821bb6c554509652f9acdff7b2` versus QSD `main` `d913310`. Separate /studio host, no imported QSD presentation, and no business data are correctly bounded. Reported local tests pass; CI/live host runtime not yet proven. Phase 1 is NOT accepted for promotion/deployment.

**Builder: correct only the following on the EXISTING `wex-studio-host` branch:**

1. **Storage privacy:** `StudioStorage.php` currently places identity JSON under WordPress uploads with only `.htaccess` and `index.php` protection. Those do not reliably block direct requests on all web servers (notably Nginx). Use a host-private, demonstrably non-web-accessible writable root or an independently verifiable server-level denial; fail closed if not protected. Ensure protection creation/errors are checked, preserve explicit admin-triggered initialization, isolation and restart persistence. Test actual direct HTTP denial on the chosen target host before claiming runtime proof.
2. **Asset containment:** `StudioAssetGateway::assetPath()` uses `str_starts_with($real, $root)` without a directory separator boundary, permitting a sibling-prefix match. Require canonical directory containment and reject traversal, sibling-prefix, and symlink escapes, including unsafe relative URL forms. Add tests for `studio-assets-evil`, `../`, and symlink escape.
3. **Recheck:** run `npm test`, `npm run docs:check`; prove `/station/` host code and existing QSD API unchanged. Push correction to same topic branch, verify exact remote SHA, update THIS file with evidence and `AWAITING REVIEWER REVIEW`, then STOP. Do not create new branches, deploy to staging/production, add WEX UI, components, data plugs, or bypass the existing API boundaries.

QSD/WordPress is **only runtime, storage infrastructure and host auth**, not WEX presentation/data architecture. WEX uses APIs/adapters, never direct WordPress DB queries. Remaining real-runtime validation must be honestly recorded as pending until performed.
