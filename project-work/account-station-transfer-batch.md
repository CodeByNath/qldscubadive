# Account Station transfer — approved batch (queued)
Status: BUILDER ACTION REQUIRED — ARCHITECTURE CORRECTIONS BEFORE ACCEPTANCE — account-station-transfer pushed to origin for independent review only
Actor when released: Builder (VS Code)
Target: CodeByNath/qldscubadive only
Source reference READ ONLY: CodeByNath/compuzign-platform at fe2e571f1bcff264bd1447e3a35bbdc5450abed3
Owner authorization: One continuous local workload, one independently testable local commit per phase; one final Reviewer handoff. No merge, staging, production, or destructive migration authorized.

## Release gate
Owner deferred Security Phase 2's live Rezdy/credential runtime validation until after Account UI relocation; it is NOT accepted. The single topic branch `docs/settings-security-roadmap` is still occupied. Do not start until Reviewer verifies safe integration of the already-reviewed Security source into main, the existing topic is lawfully retired, and one new topic branch is available. No live Rezdy key is required to start Account implementation. No new work branch while the existing one remains. Obtain current QSD main/CI truth, not stale source assumptions.

## Authority first
Read QSD AGENTS.md, docs/ai-index.md, docs/roadmap.md, Code Maps (settings-station, admin-station, station-manager, platform-identifier-station, relevant drawer/lifecycle), skills/qsd-platform-architecture/SKILL.md, StationDrawerLifecycleContract-v1.md, credential-broker-contract and current source/tests. Audit the source reference precisely; use only Account subtree as reference and do not modify CompuZign.

## Phase A — audit, identity and foundation
- Compare QSD settings/service station ownership and account source; inventory files, tests, routes and storage; confirm no collision with existing QSD records or future prefixes.
- Introduce QSD Account-owned singleton hierarchy Account → Settings → Tools → Profile (Brand), via existing PlatformIdentifierStation only; proposed prefixes QSDA, QSDAS, QSDAST, QSDASTP must pass collision and identity-policy tests.
- Port/adapt Account repository, concurrency-safe write, idempotent first-Save identity bootstrap, API controller and module; rename namespace, keys, QSD routes, permissions; never import CZ identifiers/data.
- No read-path ID minting. Test concurrency, interrupted bootstrap, fail-closed storage. Commit `account: phase A foundation`.

## Phase B — Profile / Brand
- Adapt the source's one-module Brand draft/settle/Publish/Disable/Enable flow to QSD's locked drawer/lifecycle system; existing mounted drawer identity handoff remains intact.
- Brand name/code, logo/favicon upload, list, preview and Save references; validate file types/permissions and no uncontrolled cleanup.
- Singleton carve-out: no Archive/Trash/Delete unless an explicit Owner-approved architecture decision; document deviation clearly rather than fake conformance.
- Add PHP/TS/regression coverage; commit `account: phase B profile brand`.

## Phase C — frontend and Admin integration
- Port/adapt Account registration, surface/card, drawer, API/hook, presentation using QSD shared kit. Admin only hosts; Station Manager coordinates. No parallel UI, drawer, editor, footer, notifications or identity system.
- Add Account navigation with existing registered order, ensure boot finalize and cross-station navigation/tests. Commit `account: phase C admin integration`.

## Phase D — global settings relocation
- Retain QSD's existing Settings/Security backend, encrypted credential broker, keyring/rotation, permissions, API endpoints and stored credentials; DO NOT replace with CompuZign security code or change storage/secret handling.
- Relocate *presentation/access* of global Connections & Security beneath Account Settings/Tools, then retire standalone Settings navigation only after regression proof.
- Service Meta definitions remain in QSD's owning domain/backend unchanged; preserve existing routes/data and access, but remove from global Account view as Owner excluded metafields. Service Home Create Service/Create Category stay unchanged; no AI/AOI capability is introduced.
- Security Phase 2 security invariants must remain protected by existing source and deterministic tests; live WordPress/Rezdy validation remains explicitly deferred and must never be described as passed. Test old endpoint compatibility, credentials, permission gates and route accessibility. Commit `account: phase D global settings placement`.

## Phase E — validation, handover and closeout
- Update docs/roadmap.md (authoritative handover/current state), relevant Code Maps, AI index, local instructions and architecture wording with exact implemented behavior. No extra handover. Keep files cohesive and normal <=600 lines.
- Run focused tests after each phase and from plugin root run `npm test` and `npm run docs:check` once at final candidate. Record exact phase commit SHAs/diffs, changed files, checks and remaining uncertainty.
- Commit `account: phase E documentation validation` only when all safeguards pass.
- **Stop before pushing**: report final local HEAD and commit chain to Reviewer/Owner for authorization; DO NOT push, merge, deploy or self-approve. This explicit Owner batch boundary supersedes the ordinary per-phase push handoff only for this workload; no staging or production change.

## Hard stop / rollback
At ANY phase if implementation would compromise identity permanence, ownership, credential security, lifecycle, persisted data, existing Service/Category behavior, or require destructive migration or deployment expansion: stop before that phase's commit and before any push, retain previously valid local commits, record evidence + exact blocking decision, ask Owner/Reviewer. Never silently workaround. Never continue into next phase with failing tests. Never copy full plugin or source-specific Platform IDs. Only QSD repository is writable.

## Builder handoff — all five phases built, local, awaiting authorization (2026-10-11)

One continuous local workload on topic branch `account-station-transfer` (branched from `main` at `cee882c`), one commit per phase exactly as authorised. Nothing pushed; `main`, `staging`, `Project-work-instructions` are all untouched by this workload.

**Exact local commit chain**, oldest first:

| SHA | Commit |
|---|---|
| `e23833c` | `account: phase A foundation` |
| `9403c9b` | `account: phase B profile brand` |
| `72b734f` | `account: phase C admin integration` |
| `5dad8d1` | `account: phase D global settings placement` |
| `b13b7e2` | `account: phase E documentation validation` (current local HEAD) |

**Per-phase summary** (full detail in each commit message and in `docs/code-map/account-station.md`):
- **A** — four new Platform ID prefixes (`QSDA`/`QSDAS`/`QSDAST`/`QSDASTP`); `AccountRepository` ($wpdb compare-and-swap aggregate) and `AccountIdentity` (idempotent, resumable 4-node bootstrap through the existing `PlatformIdentifierStation::ensure()` — no parallel mechanism).
- **B** — `AccountBrand` (draft/settle/Publish/Disable/Enable, a narrow two-state slice, not the full `StationLifecycle` engine) and `AccountMedia` (content-hash-addressed logo/favicon, not the WordPress Media Library).
- **C** — `resources/ts/account-station/` registered with Station Manager exactly as Service is; Home card is one `ReadBlock` (singleton, not a catalogue); drawer implements `DrawerContent` directly (one module, no generic multi-module composition); footer is `EntityActionFooter` directly, never `CanonicalEntityFooter` (whose overflow always offers Archive/Trash).
- **D** — Tools (Rezdy importer) and Security (API Keys) Settings panels are now also reachable from Account (`stationIds` gained `'account'`), additive; Services' own reachability is unchanged. Service fields (`General`) stays Services-only. Settings/Security backend, broker, keyring, rotation, `qsd/v1` routes untouched.
- **E** — `docs/roadmap.md` updated with the authoritative "Account Station transfer" status section; this handoff.

**Validation at the final candidate (`b13b7e2`):** `npm test` passes in full — typecheck; every PHP test including `tests/account-station.php` and `tests/account-brand.php`; build; JS 25/25 including `contract:account-registration` (57 checks — registration/resolution, Service/Services completely unaffected, no archive/trash/restore/delete concept anywhere under `account-station/`); `docs:check` (49 Markdown files, 20 Code Maps).

**Deliberate singleton carve-out, every phase:** no Archive/Trash/Restore/Delete route, footer control, drawer action, or action intent exists anywhere in `Modules/Account`/`account-station/`. Enforced by `contract:account-registration`'s source-level scan, not just an unregistered intent.

**Remaining uncertainty, stated rather than hidden:**
1. No fully mounted Preact-render regression exists for the Account drawer/card (the pattern `service-create-regression.mjs` uses). Coverage is real but indirect: backend through real REST-handler calls (`tests/account-brand.php`), frontend through registration/resolution (`contract:account-registration`). Neither renders `AccountDrawerHost`/`AccountCard` through an actual DOM. A human browser check on staging2 is the genuine remaining verification.
2. Live WordPress/Rezdy credential-guard runtime validation (deferred under Security Phase 2) has still not run, through this UI or any other. Not attempted; never described as passed.
3. Retiring Services' own Tools/Security reachability is explicitly not done in this batch — gated on regression proof the Owner can see, per the batch's own wording.

**Builder next action:** none — stopping here per the Owner's explicit batch authorization ("Stop before pushing... DO NOT push, merge, deploy or self-approve"). Reviewer/Owner: the five commits above are ready to inspect on the local `account-station-transfer` branch. On approval, Builder can push the topic branch to origin as a new topic branch (repository governance currently has capacity: only `main`/`staging`/`Project-work-instructions` exist remotely) and update this file to `AWAITING REVIEWER REVIEW` with the pushed SHA, exactly as the standard Builder source-push handoff requires.


## Reviewer decision — local batch handoff (2026-10-11)

**Verdict: Proceed with safeguards** for **topic-branch publication for review only**, NOT acceptance of the five phases, not permission to merge/deploy. The Owner-requested stop-before-push review has occurred. GitHub independently confirms remote `main` = `staging` = `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`, prior Security topic branch gone, and only three permanent remote heads. Five Account phase commits and tests are **Builder-reported local evidence only**; Reviewer cannot independently inspect local-only SHA/diff.

**Bounded Builder next action:** Recheck origin and local clean status; ensure `account-station-transfer` is based on approved main at `cee882c` and contains only the five declared commits in order (`e23833c`, `9403c9b`, `72b734f`, `5dad8d1`, `b13b7e2`). Push **only** `account-station-transfer` to `origin`; verify remote exact HEAD `b13b7e2`; set status `AWAITING REVIEWER REVIEW` with exact full SHAs and test evidence on this same coordination file, then STOP. Do not push main/staging, merge, deploy, start Rezdy credential validation or widen source.

**Required independent review before approval:** Inspect all five diffs and code maps against QSD station/drawer/lifecycle authority; singleton lifecycle carve-out; identity families and read/Save boundaries; AccountMedia security and WP permissions; Settings/Security ownership/API invariants; Services-specific tools/metafields separation; verify UI actual relocation vs additive reachability and old Services path retention. Builder should add focused mounted Preact drawer/card regressions or state precise test gaps when instructed after source review. **Stop** if any mismatch threatens platform architecture; no silent workaround. Live Rezdy/WordPress checks remain deferred; no production authorization.

## Builder source-push handoff (2026-10-11)

Bounded Reviewer instruction executed exactly: rechecked remote/local state, confirmed `account-station-transfer` is based on approved `main`@`cee882c` (merge-base = `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`, equal to `origin/main`) and contains exactly the five declared commits in order with a clean working tree; pushed **only** `account-station-transfer` to `origin` (no `main`/`staging` touched, no merge).

**Verified remote state:** `origin/account-station-transfer` HEAD = `b13b7e2c6671f95105ee11f08e2a4328011f6549` (new branch, confirmed via `git rev-parse origin/account-station-transfer` after push).

**Exact full-SHA commit chain, oldest first:**

| Full SHA | Commit |
|---|---|
| `e23833cbc910296706f1b3600be6e6e5602d498d` | `account: phase A foundation` |
| `9403c9b2b0f515c4cd19e7b1d62f4474cc641e37` | `account: phase B profile brand` |
| `72b734fe133099034d4ebe1abcb603436999901e` | `account: phase C admin integration` |
| `5dad8d191b924bcb7b924df48fd66b05c82f1a02` | `account: phase D global settings placement` |
| `b13b7e2c6671f95105ee11f08e2a4328011f6549` | `account: phase E documentation validation` (branch HEAD) |

**Test evidence:** unchanged from the Builder handoff above — `npm test` green at `b13b7e2` (typecheck, every PHP test incl. `tests/account-station.php`/`tests/account-brand.php`, build, JS 25/25 incl. `contract:account-registration` at 57 checks, `docs:check` 49 files/20 Code Maps). Not re-run after push since push changes remote location only, not tree content (verified identical by SHA).

**Not done, per the Reviewer's exact bound:** no push of `main`/`staging`, no merge, no deploy, no Rezdy credential validation started, no scope widened beyond the single topic-branch push and this status update.

**Builder next action:** none — stopping here. Reviewer: `account-station-transfer` is now pushed and independently inspectable on `CodeByNath/qldscubadive` at `b13b7e2`. Awaiting your review verdict.


## Independent Reviewer audit — pushed `b13b7e2` (2026-10-11)

**Verdict: Stop — architectural risk.** GitHub independently confirms topic HEAD `b13b7e2c6671f95105ee11f08e2a4328011f6549`, five commits ahead of `main=cee882c` and no main/staging changes. Read actual AccountController, AccountBrand, AccountIdentity, AccountDrawerHost, AccountMedia, Account Code Map and Settings registration, compared to main's locked StationDrawerLifecycleContract-v1 and architecture skill. Builder test passes are reported, not independently executed. Do not merge/deploy.

**Blocking differences:**
1. `AccountBrand::publish/disable/enable/settle` and AccountController's custom status mutator constitute a parallel lifecycle implementation; Account Code Map claims an Owner-approved deviation, but this workload never explicitly approved bypassing QSD's locked StationLifecycle engine or dropping Archive/Trash/Restore/Delete. The Owner expressly required Station parts to follow established Station lifecycle. Singleton permanence can be proposed, not presumed as an exemption.
2. `settings-station/register.ts` makes both global Tools and Security available under `stationIds:['services','account']`. This is additive reachability, not the requested transfer of global-only settings to Account. Owner specifically excluded Service Meta, Create Service, Create Category and AI/AOI from Account. Existing QSD Settings/Security backend/credential storage must remain untouched.
3. `AccountDrawerHost` directly composes `EntityActionFooter` and a separate editor arrangement rather than demonstrating conformity to the established canonical footer/identity-handoff/notification system. Need a precise comparison to the Service implementation; do not instantiate a second component/lifecycle contract.

**Bounded Builder correction on SAME topic branch:** Audit Service/Category against Account for lifecycle, drawer, statuses, footer, notification and identity handoff; reuse platform owners and established components wherever applicable. Provide an explicit exception proposal only for truly immutable singleton operations, with evidence and Owner decision BEFORE implementing an alternative lifecycle. Relocate global Tools/Security presentation so Services no longer offers their global panels, while Service Meta stays Services-only and security APIs/storage remain unchanged. Add mounted Preact regression for Account card/drawer and navigation/Settings route placement, plus source+PHP lifecycle transition regressions. Update Code Maps/roadmap to reflect actual implementation without invented Owner approvals. Run `npm test` and `npm run docs:check`. Do not merge/deploy/push unrelated work; stop if decision needed. Keep Account identity prefixes and fixed native binding only if they pass architecture comparison.
