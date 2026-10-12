# WEX /studio host integration — queued QSD work

Status: AWAITING REVIEWER REVIEW — Phase 1 security corrections pushed at `914002e`
Actor now: Reviewer
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


## Builder correction handoff — Phase 1 security (2026-10-12)

Bounded correction executed on the SAME `wex-studio-host` branch (no new branch opened), one new commit, fast-forward from `f0825cb`:

| Full SHA | Commit |
|---|---|
| `914002e39a8e8905ff68c5e474552ea1ddec255a` | `studio: Reviewer-requested Phase 1 security corrections` (branch HEAD) |

**Correction 1 (storage privacy) — fixed, not just mitigated.** `StudioStorage::root()` previously resolved under `wp_upload_dir()`'s basedir — inside the WordPress install directory, which is exactly what a web server's document root is configured to, so `.htaccess`/`index.php` were the *only* barrier, and the Reviewer correctly noted Nginx never reads `.htaccess` at all. Root now resolves to `dirname(ABSPATH)` — one directory *above* the WordPress install itself. No web server, Apache or Nginx, is ever configured to serve a path outside its own document root; this is a structural guarantee independent of server config, not an additional rule that could be misconfigured. `.htaccess`/`index.php` are kept as cheap defence in depth on servers that do honour them, and `ensureProtectedRoot()` now checks both writes' return values — a failed write to either fails the whole `initialize()` closed rather than silently proceeding unprotected. New test assertions (`tests/studio-storage.php`, scenario 1) prove the resolved root is structurally outside the fake `ABSPATH` fixture, and is exactly one directory above it.

**What was NOT re-verified, stated rather than implied:** "test actual direct HTTP denial on the chosen target host" is real-server/browser-level runtime proof that requires a deployed host — explicitly not authorized in this bounded correction (no deployment). What this candidate proves is the structural guarantee (the resolved path is outside the directory any document-root configuration points at) and the filesystem-level fail-closed behaviour; the live-host HTTP check itself remains pending until a staging deployment is separately authorized, exactly as the Reviewer's own closing line requires.

**Correction 2 (asset containment) — the exact bug fixed, verified real.** `StudioAssetGateway::assetPath()`'s check was `str_starts_with($real, $root)` with no boundary, so a sibling directory merely named with `$root`'s name as a string prefix (e.g. `studio-assets-evil/` against `studio-assets/`) would incorrectly pass containment. Proved this was a real, not theoretical, defect: temporarily restored the exact pre-correction file (`git show f0825cb:...StudioAssetGateway.php`) and reran the new tests against it — scenario 6 (the sibling-prefix case) failed exactly as expected, enqueuing `../studio-assets-evil/secret2.js` as the WEX bundle; scenario 7 (the symlink case) passed even against the old code, because `realpath()` already resolved the symlink to its outside target before the (buggy) prefix check ran, and that outside path didn't happen to share a string prefix with `root` in this fixture — so symlink resolution was already safe, and scenario 7 is confirmatory coverage rather than a second bug this correction fixes. Restored the corrected file and reconfirmed all tests pass. The check now requires `$real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)`. Three named scenarios exist: scenario 5 (pre-existing, `../`), scenario 6 (new: sibling-prefix `studio-assets-evil/` — the one that actually caught the reported bug), scenario 7 (new: a real symlink planted inside `studio-assets/` pointing outside it).

**Test restructuring required by the fix itself:** two scenarios from the original `tests/studio-storage.php`/`tests/studio-storage-rest.php` (the unwritable-root fail-closed path, and the uninitialized-store REST rejection) previously worked by reassigning a stubbed `wp_upload_dir()` global mid-script to point at a second fixture location. Since the corrected design reads the real PHP constant `ABSPATH` instead, and constants cannot be redefined within one process, those two scenarios now live in their own dedicated files (`tests/studio-storage-unwritable-root.php`, `tests/studio-storage-rest-uninitialized.php`) with their own fixture `ABSPATH`. No coverage was dropped — each assertion that existed before still exists, just relocated.

**Verified unchanged, not assumed:** `git diff --stat f0825cb -- src/Modules/AdminStation src/Core/AssetLoader.php src/PlatformIdentifier src/Modules/Account src/Modules/Service src/Modules/Settings` is empty — zero lines changed in `/station/`'s own code or any existing QSD module/API by this correction. The full `git diff --stat f0825cb` touches only `StudioAssetGateway.php`, `StudioStorage.php`, `CLAUDE.md`, the five `tests/studio-*` files, and two Markdown docs.

**Validation at `914002e`:** `npm test` green in full — typecheck; every PHP test including two new files (`tests/studio-storage-unwritable-root.php`, `tests/studio-storage-rest-uninitialized.php`) and three rewritten ones (`tests/studio-storage.php`, `tests/studio-storage-rest.php`, `tests/studio-asset-gateway.php` — the latter gaining the two new escape-path scenarios); build; JS **27/27** unchanged (backend-only correction, no TS touched); `npm run docs:check` (52 Markdown files, 21 Code Maps — `docs/code-map/studio-host.md` and `src/Modules/StudioHost/CLAUDE.md` updated to describe the corrected design).

**Confirmed, per the Owner's standing constraint:** WordPress remains runtime/storage/host-auth only — `StudioStorage`'s new root is still filesystem storage WordPress's own install directory sits beside, not a new database or external store; WEX still reaches QSD exclusively through the same `qsd/v1/studio/*` REST routes (no direct WordPress DB queries are exposed, and none were added); no legacy QSD UI, drawer, or presentation code is referenced anywhere in Studio Host (re-verified by the existing structural scan in `tests/studio-host.php`, unaffected by this correction and still passing).

**Not done, per this correction's own bound:** no new branch (same `wex-studio-host`); no push to `main`/`staging`; no deployment; no WEX UI/components/data plugs; no API-boundary change or new route. Live-host HTTP-denial verification remains honestly recorded as pending, not performed.

**Builder next action:** none — stopping here. Reviewer: `wex-studio-host` is pushed at `914002e`, independently inspectable, addressing both numbered corrections. Awaiting verdict.


## Reviewer verdict and bounded correction — 12 October 2026

**Verdict: Proceed with safeguards.** Reviewer inspected pushed `wex-studio-host` at `f0825cbeb9a919821bb6c554509652f9acdff7b2` versus QSD `main` `d913310`. Separate /studio host, no imported QSD presentation, and no business data are correctly bounded. Reported local tests pass; CI/live host runtime not yet proven. Phase 1 is NOT accepted for promotion/deployment.

**Builder: correct only the following on the EXISTING `wex-studio-host` branch:**

1. **Storage privacy:** `StudioStorage.php` currently places identity JSON under WordPress uploads with only `.htaccess` and `index.php` protection. Those do not reliably block direct requests on all web servers (notably Nginx). Use a host-private, demonstrably non-web-accessible writable root or an independently verifiable server-level denial; fail closed if not protected. Ensure protection creation/errors are checked, preserve explicit admin-triggered initialization, isolation and restart persistence. Test actual direct HTTP denial on the chosen target host before claiming runtime proof.
2. **Asset containment:** `StudioAssetGateway::assetPath()` uses `str_starts_with($real, $root)` without a directory separator boundary, permitting a sibling-prefix match. Require canonical directory containment and reject traversal, sibling-prefix, and symlink escapes, including unsafe relative URL forms. Add tests for `studio-assets-evil`, `../`, and symlink escape.
3. **Recheck:** run `npm test`, `npm run docs:check`; prove `/station/` host code and existing QSD API unchanged. Push correction to same topic branch, verify exact remote SHA, update THIS file with evidence and `AWAITING REVIEWER REVIEW`, then STOP. Do not create new branches, deploy to staging/production, add WEX UI, components, data plugs, or bypass the existing API boundaries.

QSD/WordPress is **only runtime, storage infrastructure and host auth**, not WEX presentation/data architecture. WEX uses APIs/adapters, never direct WordPress DB queries. Remaining real-runtime validation must be honestly recorded as pending until performed.
