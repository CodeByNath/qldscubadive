# WEX /studio QSD staging — consolidated work package

Status: AWAITING REVIEWER REVIEW
Actor: Reviewer (CI/runtime evidence); separately, a browser-capable operator and host administrator for the still-unavailable checks
Phase: `staging` promoted and pushed, triggering the real deploy-staging run; no CI/runtime/browser proof from this session
Repository: `CodeByNath/qldscubadive` only

## Accepted baseline
QSD `main` `69a106bc7be0de7325f3010664991356e0b89f18`; `staging` `29503aecaf262265b379e14ca8c16485466dd088`. WEX approved source `main` `02bbfb0e05702ab2b8beee629d2c328644d91c15`; WEX host bundle candidate `8409ae51f74dfe9ffb22b1b284c2a44c1a5eba8f`. QSD Studio Host source and WEX Phase 1A accepted; completed topic branches retired. Past review/correction/promote evidence remains in Git history of this coordination file. No staging install/runtime proof yet.

## Owner approval and outcome
Owner authorises QSD staging-only preparation/installation and verification of the existing independent WEX Dashboard and its static resources, plus an evidence-led logical resource-folder plan. One larger Builder batch, **commit logically after each phase and continue without micro-approval**. Push the completed governed topic branch and coordination handoff together only at the final phase boundary. **Stop early only for architecture/security compromise, missing indispensable capability/host approval, destructive change, or failed hard gate.** No production deployment.

## Preflight and authority
Before work, read QSD `AGENTS.md`, `docs/ai-index.md`, `docs/roadmap.md`, `docs/foundation/repository-governance.md`, `docs/code-map/studio-host.md` and linked source/deployment authority; read WEX host bundle installer guidance as external integration input. Verify exact remote heads, origin, allowed branch capacity, staging pipeline, current host access and required runtime tools. If essential end-to-end capability is missing, stop before partial execution and report the capable surface required.

## Internal Builder phases (separate local commits)
1. **Baseline and asset mapping.** Confirm QSD route/access/mount/gateway and WEX release manifest contract; record existing folder/resource owners and repeatable staging asset installation steps. Do not invent new runtime paths.
2. **Staging packaging.** Prepare/copy approved WEX host bundle contents into QSD `wp-content/plugins/qsd-platform/studio-assets/` with hashed relative assets and `manifest.json`; keep QSD `/station/` untouched. Ensure source-vs-generated artifact and deployment pipeline compatibility. No QSD business records or API plugs.
3. **Resource organisation contract.** Document mapping: GitHub owns source/versioned public bundles; QSD owns persistent media/user assets; private WEX identity space is separate. Reuse existing QSD facilities and only implement a minimal change if required by demonstrated staging needs and already authorised architecture. No new generic resource API, speculative directories or user-folder creation.
4. **Staging rollout and runtime proof.** Using approved staging mechanism only, verify target host, auth/unauth route, WEX CSS/JS/icons, responsive sidebar, keyboard/focus, missing-asset/mount failure, and unchanged `/station/`. For `QSD_STUDIO_PRIVATE_ROOT`, require host-admin verified private writable directory and negative direct-HTTP test on actual server; never initialise storage. A missing safe root or inability to prove denial blocks any privacy certification; report separately and do not bypass.
5. **Checks and handoff.** Run `npm test`, `npm run docs:check`, relevant browser checks and pipeline evidence. Record exact local phase commits, final remote SHA, changed files, staging deployment SHA/URL and observable success/failures. Update THIS work file to `AWAITING REVIEWER REVIEW` and stop for independent Reviewer acceptance.

## Hard exclusions and gates
Never modify WEX repository source, QSD `/station/` behaviour, production, host secrets, business data, identity bootstrap, or atomic identity adapter. Existing QSD single-blob identity API does **not** meet WEX atomic reserve/transition/lookup contract: integration stays DEFERRED. A new adapter, credential change, questionable public/private path, service outage, production publication, or material scope expansion requires Reviewer/Owner decision, not improvisation. Do not confuse commit, branch push, CI, staged runtime and live proof.

## Acceptance and notification
Notify Owner only when Reviewer independently confirms the staging Dashboard, every required static resource, access denial, interaction/a11y basics and `/station/` preservation. A passing build alone is insufficient. No autonomous background monitor is implied.

## History (full detail in this file's Git history on this branch)

Phases 1–3 (WEX bundle built read-only from `CodeByNath/WEXdesigns`, copied into `studio-assets/`, Code Map updated) landed on topic `wex-studio-staging-package` and were pushed; Reviewer verdict "Proceed with safeguards" but flagged a deployment hard gate — the staging `rsync --delete` plugin include list omitted `/studio-assets/***`, so the bundle would never deploy and could be deleted. Builder fixed the allowlist, proved it against a disposable local fixture, pushed the correction (`2578e15`, 3 commits ahead of `main`). Capability gap 2 (no staging host/SSH/browser access in this session) then blocked Phase 4 twice in a row even after Reviewer named the execution assignment (GitHub Actions runner + host administrator + browser-capable operator), since none of those three surfaces could be demonstrated from here (confirmed via failed `gh auth status`, no SSH, no browser tool). Owner then confirmed they hold the GitHub Actions staging secrets/settings themselves and authorised a bounded runner-side promotion attempt contingent on a dry-run/safety preflight.

## Builder preflight and promotion — staging pushed (2026-10-12)

**Ancestry/diff preflight.** `staging` (`29503ae`) was a strict ancestor of topic `wex-studio-staging-package` (`2578e15`): zero commits existed on `staging` that weren't already in the topic, so the promotion is a lossless fast-forward, no merge/rebase, nothing discarded. The resulting diff (116 files, +2230/-189) is larger than just the 3 WEX commits because `staging` was also 4 commits behind `main`: three already-accepted `studio:` commits (the QSD-owned `/studio/` host infrastructure — `StudioAssetGateway.php`, `StudioHostModule.php`, `StudioStorage.php`, `StudioStorageController.php`, templates — that the WEX bundle requires to function at all, plus their Reviewer security corrections) and one unrelated `account:` staging bugfix already on `main`. All four predate this coordination file's scope and were not re-reviewed here; they are promoted only as an unavoidable consequence of the topic being cut from current `main`.

**Rsync/workflow preflight.** Confirmed on the topic tip: `--include='/studio-assets/***'` present in the plugin rsync include list; `--delete` still scoped only to the two destination subtrees (`wp-content/plugins/qsd-platform/`, `wp-content/themes/qsd-shell/`); the pre-existing `STAGING_WP_PATH` hard-coded-expected-value guard (`/home/customer/www/staging2.qldscubadive.com.au/public_html`) is unchanged and still runs before any rsync. Fixture-proof from the earlier correction (replace-stale/leave-uploads-untouched/dev-files-excluded) still applies unchanged since the workflow file hasn't moved since that proof.

**Rollback plan.** No blue/green or snapshotting exists in this pipeline; rollback = push a new revert commit to `staging` (never force-push, a permanent branch) and let the same `deploy-staging` job redeploy the prior tree via the same rsync, which is idempotent over source content.

**Action taken:** fast-forwarded local `staging` to `2578e15` and pushed to `origin/staging` (`29503ae..2578e15`), which triggers the real `deploy-staging` GitHub Actions job against the live staging host using repository-configured secrets. (One local merge/status command was first denied by this session's own auto-mode classifier — `[Modify Shared Resources]`, then `[Production Deploy]` on a plain `git status` — and succeeded on explicit user retry; not a project-governance block.)

**What is NOT verified from this session:** the Actions run's pass/fail (no `gh` auth/API access here), the live `/studio/` route, asset loads, auth/anon behaviour, responsive/sidebar/keyboard checks, `/station/` non-regression, and the `QSD_STUDIO_PRIVATE_ROOT` host-admin/HTTP-denial proof. All require, per the Reviewer's own assignment, a browser-capable operator and a host administrator outside this session. No identity/storage initialization performed or attempted.

Status set to `AWAITING REVIEWER REVIEW` for the pushed `staging`/topic candidate (`2578e15`) and this coordination update, per protocol — but final acceptance also needs the runtime/browser/host evidence above, which only a differently-capable operator can supply.
