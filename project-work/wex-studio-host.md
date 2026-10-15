# QSD /studio — WEX Dashboard staging integration

Repository: `CodeByNath/qldscubadive`
Status: BUILDER ACTION REQUIRED — Phase 1F corrected WEX Logo staging install
Actor: **BUILDER:CODER (Claude)** — QSD staging installation and visual proof
Phase: 1F — staging installation and authenticated browser proof

## Owner goal
Show the **latest approved WEX Dashboard refinements** in the actual QSD staging `/studio`, promptly. No duplicate UI implementation. Previous staging Phase 1E was visually accepted with real QSD Profile Logo rendered in the Header. Latest WEX Header/Sidebar refinements are not yet proven on the QSD host.

## Authority and exact source
- Read QSD `project-work/AGENTS.md`, `project-work/PROJECT-RULES.md`, relevant `main` host-source and deployment authority, and the accepted Phase 1E QSD integration/diff.
- Current WEX approved source: `CodeByNath/WEXdesigns` `main` at **`3c9e136e12be218b2bfac068594b92f4ddfc5865`** (Phase 2C Logo 64px containment). Earlier QSD deployed bundle was built from `943e7bea2e102e76475784114e311eac5ca37259` and lacks the approved correction.
- QSD existing host mount + real Profile Logo data binding must remain unchanged. WEX owns presentation, QSD owns host data/route/deployment.

## ONE next Builder action — Claude in QSD
1. Confirm existing QSD `staging` integration and applicable branch-capacity rule; use existing permitted integration topic if safely reusable. Do not remove unrelated work.
2. Rebuild/install only the WEX Studio host distribution generated from the **exact approved SHA** using the previously verified Phase 1E method. Update QSD's existing asset/manifest references; no new app shell, copied source UI, local CSS, WEX token, schema, data API, identity, or Profile change.
3. Run required QSD integration checks. Push the QSD implementation, deploy to **staging only**, record exact QSD commit, manifest asset filenames and workflow outcome.
4. Verify the deployed authenticated URL `https://www.staging2.qldscubadive.com.au/studio/`: real Profile Logo image in Header; updated Header/Sidebar appearance; expand/collapse/hover interactions; compact drawer; keyboard/focus and responsive behaviour. Record screenshot/DOM/browser evidence. **Never call build or CI alone 'live'.**
5. Update this SAME work file to `AWAITING REVIEWER REVIEW` with exact pushed SHA, tests, workflow, browser evidence and limitations; **stop**.

## Stop gates
If permissions, branch capacity, required contract or deployment access blocks completion, report the **specific blocker** here and stop; do not invent a WEX delivery architecture. No QSD production deploy, no database/identity changes. WEX UI changes must be sent to WEX Builder, not implemented here.

## Future permanent delivery
WEX Phase 2B dynamic versioned-runtime delivery design is **deferred** until this staging result is accepted or deferred. It is a **WEX Codex task**, not part of this QSD phase. Do not assign Codex to QSD or Claude to WEX.

## Historic acceptance
Phase 1E staging Logo visually accepted 14 Oct 2026. Evidence, prior staging SHA `1e52bf6`, manifest and workflow are retained in Git history of this file.

## Builder — Phase 1F WEX bundle rebuilt from accepted main `943e7be`; pushed and deployed; browser proof still needed (15 Oct 2026)

**Source check:** `git ls-remote` confirmed `CodeByNath/WEXdesigns` `main` HEAD is exactly `943e7bea2e102e76475784114e311eac5ca37259`, matching this work file's approved SHA, before cloning.

**Build:** Reused existing topic branch `wex-studio-staging-package` (branch cap: `main`/`staging`/`Project-work-instructions`/this one — no new branch opened). Cloned WEX at that SHA, `pnpm install --frozen-lockfile` then `pnpm --filter @weerax/web-runtime build:studio-host`. Output contains `wexStudioHostOptions` and `wex-logo__image`; WEX's own `test:studio-host` passes 4/4.

**Install:** Replaced only `studio-assets/assets/` and `manifest.json`; `README.md` and every font file are byte-identical — diff is exactly the new JS/CSS entry hashes and the manifest pointer, nothing else.

**Checks:** `npm run typecheck` clean; all 23 tracked `tests/*.php` files pass individually (the npm `test:php` script's glob also picks up the pre-existing untracked `" 2"`-suffixed clutter files, one of which — `tests/studio-asset-gateway 2.php` — now fatals on a missing class from a newer, unrelated branch state; confirmed via `git ls-files`/`git status` that this file is not tracked and not part of this change); `npm run build` clean; `npm run test:js` 27/27; `npm run docs:check` shows only the same single pre-existing, already-documented clutter-caused failure, nothing new.

**Push/deploy:** Committed `e726eb1` on `wex-studio-staging-package`, pushed, verified on remote. Fast-forwarded `staging` to `e726eb1`, pushed, verified on remote. CI run [38684103885](https://github.com/CodeByNath/qldscubadive/actions/runs/38684103885): `test` and `deploy-staging` jobs both green, including SSH login check, staging-target verification, and smoke check.

**What's left:** no browser/login capability this session, so step 4 (real Logo image, updated Header/Sidebar appearance, expand/collapse/hover, compact drawer, keyboard/focus, responsive behaviour on authenticated `https://www.staging2.qldscubadive.com.au/studio/`) is not yet confirmed. Stopping here with source/deploy evidence per this phase's own instruction; Reviewer or Owner needs one authenticated browser check to close this out.


## Owner visual rejection — 2026-10-16 (CURRENT)

Owner's actual authenticated QSD staging `/studio/` screenshot **fails** the Phase 1F visual gate: real scuba logo is rendered at oversized intrinsic dimensions, extends beyond its intended header/logo compartment and overlays sidebar/menu and header ('Studio'). Phase 1F is NOT accepted. Successful CI/deploy only proves delivery, not UI functionality. Dynamic Profile logo data evidently arrives and image renders; avoid changing Account Profile/API/Platform IDs. Earlier accepted Logo state no longer demonstrates acceptable containment in newly built bundle.

**One bounded QSD Builder investigation:** use current WEX-approved artifact SHA, exact QSD staging manifest JS/CSS filenames and staging DOM/computed styles to identify whether CSS asset failed to load, host document/style-order collision exists, or WEX bundle itself lacks containment for arbitrary real logos. Compare against WEX working browser build using same real image/aspect ratio. Verify the actual rendered image naturalWidth/Height versus computed width/height, containing element's box/overflow, and loaded rules/assets; capture source and network/DOM evidence. Fix only a demonstrated QSD host/package integration defect in QSD; **if source WEX logo/layout styling is defective, do not rewrite WEX UI inside QSD**—record exact defect and request WEX Builder correction, then repackage only the resulting approved fixed WEX artifact. Do not re-run a blind rebuild of unchanged approved SHA. Check cropped/non-square logos and desktop/mobile sidebar with screenshot before claiming pass. Deployment remains staging-only within existing authorised paths; no production deploy. Keep this SAME file and note final verified evidence / blocked reason.

## Current handover — Owner clarification, 16 October 2026
**QSD owns the actual scuba Logo image**, stored in QSD Profile and supplied by its host binding. WEX owns only the reusable Logo presentation/renderer, not image assets, QSD data or business rules. The Owner's authenticated screenshot shows QSD data binding succeeds but the rendered image overflows. No Profile/API/Logo image data changes are authorised.

**Immediate BUILDER:CODER (Claude), QSD only:** First inspect the existing staging manifest and loaded JS/CSS to confirm whether the installed artifact predates WEX accepted `main` `3c9e136e12be218b2bfac068594b92f4ddfc5865`. If outdated, build and install the exact approved WEX Studio host distribution using the established Phase 1F process, with manifest/assets and no QSD UI/style recreation. Push/deploy to QSD **staging only**, run existing checks, and verify the real QSD Profile image remains within a maximum 64×64px rendered area in collapsed, hovered, expanded and compact sidebar states, without overlap. Record QSD SHA, manifest asset hashes, CI/deployment and authenticated browser DOM/screenshot evidence. If updated CSS is loaded and the issue remains, stop and record computed DOM/cascade evidence; route a demonstrated WEX presentation defect to WEX Builder, not local QSD CSS. Mark this same work file AWAITING REVIEWER REVIEW only with real evidence. No production deployment or unrelated work. WEX topic-branch housekeeping is separate and is not a blocker for this QSD install of the already accepted WEX main SHA.

## Builder — BLOCKED, missing execution capability (16 October 2026)

**Source check passed:** `git ls-remote` confirmed `CodeByNath/WEXdesigns` `main` HEAD is exactly `3c9e136e12be218b2bfac068594b92f4ddfc5865`; cloning it at that SHA showed commit subject `fix(wex): contain logo artwork within brand geometry` (`packageManager: pnpm@11.16.0`), consistent with the Owner's described containment fix. Confirmed the deployed staging bundle (built from `943e7bea2e102e76475784114e311eac5ca37259`) predates this approved SHA — rebuild is required, matching the work file's own diagnosis.

**Blocker:** this session's `pnpm` is unavailable locally; `npx pnpm@11.16.0 install --frozen-lockfile` against the cloned WEXdesigns checkout (with and without `--ignore-scripts`) is refused by this execution surface's own Auto Mode classifier, reason `[Code from External]`. No reasonable variant of the install command was permitted. This stops the Phase 1F rebuild/install step before any build, push, or deploy — nothing new was pushed to `wex-studio-staging-package` or `staging`.

Per `project-work/AGENTS.md` Executor capability preflight: keeping ownership with **BUILDER:CODER (Claude)**; this is a missing execution capability on this surface, not a project/architecture decision. Required execution surface: a Claude Code session whose Auto Mode (or equivalent) permission settings allow installing dependencies for code cloned from `CodeByNath/WEXdesigns` (an approved internal repo), or an equivalent surface where that install can run. Reported in chat per the chat-reporting exception (blocks the authorised action from landing). Stopping here; Status remains `BUILDER ACTION REQUIRED` until this capability is available or the Owner directs an alternate execution path.
