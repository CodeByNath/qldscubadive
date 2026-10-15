# QSD /studio — WEX Dashboard staging integration

Repository: `CodeByNath/qldscubadive`
Status: AWAITING REVIEWER REVIEW
Actor: **REVIEWER** — independently verify pushed bundle; authenticated browser proof still needed
Phase: 1F — staging installation and authenticated browser proof

## Owner goal
Show the **latest approved WEX Dashboard refinements** in the actual QSD staging `/studio`, promptly. No duplicate UI implementation. Previous staging Phase 1E was visually accepted with real QSD Profile Logo rendered in the Header. Latest WEX Header/Sidebar refinements are not yet proven on the QSD host.

## Authority and exact source
- Read QSD `project-work/AGENTS.md`, `project-work/PROJECT-RULES.md`, relevant `main` host-source and deployment authority, and the accepted Phase 1E QSD integration/diff.
- WEX approved source: `CodeByNath/WEXdesigns` `main` at **`943e7bea2e102e76475784114e311eac5ca37259`**; WEX CI `38673046456` passed.
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
