# WEX /studio QSD staging — consolidated work package

Status: BLOCKED — DECISION REQUIRED
Actor: Owner (capability gap 2 only — Phase 4 execution surface, below); QSD Builder resumes Phase 4/5 once resolved
Phase: Phases 1–3 pushed for review; Phase 4 staging rollout gated on deployment safety correction
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

## Builder progress — Phases 1–3 complete, gap 1 resolved (2026-10-12)

Full capability-gap detail (classifier denial, WEXdesigns investigation, exact SHAs) is in this file's Git history on this branch; summarized here. Owner approved retrying gap 1: built the WEX bundle read-only from `CodeByNath/WEXdesigns` `main` `02bbfb0e` (candidate commit `8409ae5`) in an isolated scratchpad clone — `pnpm install`, then building workspace deps (`@weerax/schemas`, `@weerax/wex`, `@weerax/catalogue`, `@weerax/ui`, a build-order step the WEX install doc omitted) before `build:studio-host` succeeded. Nothing written back to WEXdesigns; only build output copied out.

New, not-yet-pushed topic branch `wex-studio-staging-package` off `main` `69a106b`:
- `70ea835` (Phase 2) — copies the built `manifest.json` + `assets/` into `wp-content/plugins/qsd-platform/studio-assets/`. `/station/` untouched; no API/storage/identity wiring.
- `6e86691` (Phase 3) — `docs/code-map/studio-host.md`: records the populated bundle and the three-way resource split (GitHub owns WEX source/build; QSD media/uploads untouched; `QSD_STUDIO_PRIVATE_ROOT` the one private identity root). No new generic resource API or directory.

Phase 5's non-host checks re-run clean after clearing stray local `node_modules/@types/* 2` duplicate artifacts (pre-existing local cruft, not committed): `npm test` full green, `npm run docs:check` passes. No staging deploy, storage init, or host contact performed.

**Phase 4 still blocked on capability gap 2** (no staging host/browser access in this environment). Branch held locally pending Owner's call on Phase 4's execution surface, per "push only at the final phase boundary" — unless Owner prefers an interim push now for early Reviewer visibility of Phases 1–3.


## Reviewer verification — 12 October 2026 (pushed Phase 1–3)

**Verdict: Proceed with safeguards.** Independently verified pushed `wex-studio-staging-package` at `6e866917b10f314927d848b9cfd948e351abc286`, two commits ahead of `main` `69a106b`. Candidate adds `studio-assets/manifest.json`, hashed WEX build resources, and Code Map metadata; Builder reports `npm test` and `npm run docs:check` green, not independently rerun here. No deployed/runtime proof.

**Deployment hard gate:** current `.github/workflows/ci-deploy.yml` staging `rsync --delete` plugin include list omits `/studio-assets/***`, meaning copied WEX resources will not be deployed and the destination may be deleted. Builder must correct the allowlist for only the approved WEX static resources and prove deployment behavior against a disposable fixture before staging push. Keep unrelated persistent resource directories out of deletion scope; inspect stage target and any pre-existing data first. No novel deployment platform: existing GitHub Actions staging pipeline is the approved execution surface, with a capable credentialed runner/host administrator for runtime checks.

**Builder bounded next step (same topic branch):** fix and test manifest/asset deployment in existing workflow; commit correction; run QSD checks; verify target exactly staging. Then continue staging-only rollout only if all capability, private-root protection, and non-destructive gates are satisfied. `QSD_STUDIO_PRIVATE_ROOT` requires host-admin verified outside-webroot configuration and HTTP-denial proof; no identity initialization. If unavailable, stop and report host-access blocker, without claiming staged success. Re-verify authenticated/anonymous routes, assets, responsive/sidebar/keyboard, and `/station/` unaffected. Final pushed SHA and coordination update to `AWAITING REVIEWER REVIEW`; stop. No production changes.
