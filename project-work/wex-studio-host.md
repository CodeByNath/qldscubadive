# WEX /studio QSD staging — consolidated work package

Status: BLOCKED — DECISION REQUIRED
Actor: Owner (two capability decisions below); QSD Builder resumes once resolved
Phase: Staging preparation and verification; final Reviewer handoff
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

## Builder capability block — Phase 1/2/4 preflight (2026-10-12)

Preflight performed before opening any topic branch or changing QSD source, per "stop before partial execution and report the capable surface required." No QSD repository file was changed; no topic branch was created.

**Confirmed reachable (read-only, external integration input):** `https://github.com/CodeByNath/WEXdesigns.git` HEAD/`main` = `02bbfb0e05702ab2b8beee629d2c328644d91c15`, matching the Accepted baseline exactly. Cloned read-only into an isolated scratchpad directory (never written back to). Bundle candidate commit `8409ae51f74dfe9ffb22b1b284c2a44c1a5eba8f` ("feat(studio): add portable dashboard host assets") located on that `main` history and confirmed as WEX source, not a prebuilt artifact: it adds `apps/web-runtime/src/studio-host.js`, `apps/web-runtime/vite.studio-host.config.ts`, and `apps/web-runtime/studio-host/README.md`. That README's documented install contract matches QSD's existing `studio-assets/README.md` contract exactly — `manifest.json` shaped `{ "entry": "assets/<name>-<hash>.js", "styles": ["assets/<hash>.css"], "version": "<string>" }`, built via `pnpm --filter @weerax/web-runtime build:studio-host` into `apps/web-runtime/studio-host-dist/`, whose contents (not the directory) are copied into QSD's `studio-assets/`. This satisfies Phase 1's manifest-contract confirmation.

**Capability gap 1 (Phase 2 — staging packaging):** Producing the actual bundle requires running `pnpm install` and the Vite build against the cloned WEXdesigns source. The Claude Code auto-mode classifier denied this (`Reason: [Untrusted Code Integration]`) when attempted in the isolated scratchpad clone. No workaround was attempted. Required to proceed: Owner either (a) grants one-time approval for `npx pnpm@11.16.0 install` + `pnpm --filter @weerax/web-runtime build:studio-host` to run against the read-only scratchpad clone (output only ever copied into QSD `studio-assets/`, nothing written back to WEXdesigns), or (b) supplies the already-built `studio-host-dist/` contents directly.

**Capability gap 2 (Phase 4 — staging rollout and runtime proof):** This execution environment has no staging host credentials/SSH, no browser automation, and no way to perform a host-admin-verified private-writable-directory check or a negative direct-HTTP test against the live server. These are indispensable per the work file's own Phase 4 gate ("A missing safe root or inability to prove denial blocks any privacy certification; report separately and do not bypass"). Required to proceed: Owner specifies the actual execution surface for Phase 4 (host-admin-supervised session, a different tooled environment, or Owner-performed steps with evidence handed back for Reviewer).

No QSD architecture, security, or scope question is raised here — both gaps are tooling/authority preflight only, per `project-work/AGENTS.md` "Executor capability preflight." Builder keeps ownership and resumes Phase 1 commit + Phase 2 packaging immediately once gap 1 is resolved; Phase 4 remains blocked until gap 2 is resolved.
