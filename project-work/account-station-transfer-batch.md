# Account Station transfer — approved batch (queued)
Status: AWAITING REVIEWER REVIEW — Account source integrated into main at `29503ae`
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

**Verdict: Stop — architectural risk.** Blocking differences found: (1) `AccountBrand`'s publish/disable/enable constitute a parallel lifecycle implementation never explicitly approved as a Owner exception to the locked `StationLifecycle` engine; (2) the Settings `stationIds` change was additive reachability (`['services','account']`), not the actual relocation the batch asked for; (3) `AccountDrawerHost`'s footer composition needed precise comparison to Service's canonical pattern, not assumed conformity. Bounded correction ordered: reuse platform lifecycle components wherever applicable and propose any true singleton exception with evidence before implementing an alternative; make the Settings relocation real (Services no longer offers the global panels); add mounted Preact regression for the Account card/drawer plus source+PHP lifecycle regressions; update docs to remove invented Owner approvals; keep `npm test`/`docs:check` green; no merge/deploy/push of unrelated work.

## Builder correction handoff (2026-10-11)

Bounded correction executed on the same topic branch, two new commits:

| Full SHA | Commit |
|---|---|
| `e356a340eac46d63d344824a4b243ffb0d98af83` | `account: reviewer correction — StationLifecycle reuse, Settings relocation, mounted regressions` |
| `73706c36893ce49e7c8374b70188f9e84601b6b2` | `account: reviewer correction — docs reflect actual implementation, no invented approvals` (branch HEAD) |

**Verified remote state:** `origin/account-station-transfer` HEAD = `73706c36893ce49e7c8374b70188f9e84601b6b2` (fast-forward push from `b13b7e2`, confirmed via `git rev-parse origin/account-station-transfer`). No `main`/`staging` push, no merge.

**Finding 1 (lifecycle) — fixed, not exempted.** `AccountBrand::publish()` now calls `StationLifecycle::statusRouteTransition($current, 'active', $previous)` directly; `disable()` guards with `StationLifecycle::isLive($current)` — the identical calls `ServiceController`'s own `/status` route makes. `tests/account-brand.php` adds direct equality assertions against `StationLifecycle`'s own output, not just AccountBrand's wrapper. This resolves the "parallel implementation" finding for the `active`/`disabled` subset Account actually uses. It does **not** resolve whether Account should also wire Archive/Trash/Restore/Delete like every other Station: that remains an **open exception proposal**, not something this fix presumes settled. Evidence for the proposal: (a) the batch's own original Phase B line — "no Archive/Trash/Delete unless an explicit Owner-approved architecture decision" — read by the Builder as pre-authorizing the omission as the default, which the Reviewer's audit held is not itself that authorization; (b) `CanonicalEntityFooter`'s non-bin branch unconditionally places Archive (and Trash, unless new) in its overflow with no suppression option, so reusing it as written would force those actions into a singleton's footer — adding a suppression option is a shared-component change touching Category and Package Family too, not an Account-local decision. Docs now state this as open rather than settled either way; the Builder takes no further position and awaits an explicit Reviewer/Owner call.

**Finding 2 (Settings relocation) — fixed as instructed.** `settings-station/register.ts`'s `settings.rezdy-importer`/`settings.api-keys` `stationIds` changed to `['account']` only. Services' own Settings tab now presents `General` only (Service fields, creation launchers). The panels, their data, and the credential-broker backend (`Security/`, `Connections/`) are byte-for-byte unchanged — only which Station's array reaches them moved. The detailed Tools/Security behavioural regression that used to mount through Service (`scripts/services-settings-regression.mjs`, 11 numbered scenarios covering write-only secrets, rotation, connection test, disconnect) now mounts through Account instead (new `scripts/account-settings-regression.mjs`), against the identical panels and backend; the Services script is trimmed to its remaining General-only scope (4 scenarios). `contract:account-registration` and `contract:settings-station` assertions rewritten to match the real post-relocation state (Services: `general` section only; Account: `tools,security` only).

**Finding 3 (footer) — compared, not changed.** `AccountDrawerHost` continues to compose `EntityActionFooter` directly rather than `CanonicalEntityFooter`, now with the comparison made explicit in code and docs (see Finding 1's evidence (b)). New `scripts/account-mounted-regression.mjs` mounts the REAL `AccountCard` and `AccountDrawerHost` (esbuild + happy-dom + Preact, same technique as `service-disable-enable-regression.mjs`) and proves through actual rendered DOM — not inspected props — that Save, Publish, Disable and Enable all work via real button clicks, and that clicking the real (disabled) overflow chevron never opens an Archive/Trash menu, on any status.

**Documentation — invented-approval language removed.** "Deliberate, Owner-approved deviation" (account-station.md) and "additive relocation... Services keeps its own reachability" (settings-station.md, both CLAUDE.md files, roadmap.md) are replaced with accurate, unresolved framing: StationLifecycle reuse stated as fact; the Archive/Trash omission stated as an open exception proposal; the Settings move stated as an actual relocation, not an addition.

**Validation at `73706c3`:** `npm test` green — typecheck; every PHP test (`tests/account-brand.php` now 39 assertions incl. the new `StationLifecycle` parity checks); build; JS **27/27** (added `regression:account-settings`, `regression:account-mounted`; `contract:account-registration` and `contract:settings-station` re-verified against the new state); `docs:check` (49 Markdown files, 20 Code Maps, both touched Code Maps re-trimmed under the 600-word cap).

**Still not done / still open, stated rather than hidden:**
1. The Archive/Trash/Restore/Delete exception for Account remains an open question for explicit Reviewer/Owner decision — not resolved by this correction, which only removed the *inline-reimplementation* half of the original finding.
2. Live WordPress/Rezdy credential-guard runtime validation remains deferred; not attempted; never described as passed.
3. No fully mounted regression exists yet for the Account Settings-section *placement itself inside the Admin Station shell* (nav → Account → Settings → Tools/Security tab clicks through the real shell chrome) — `regression:account-mounted` and `regression:account-settings` each mount the relevant component directly, which is the same technique every existing mounted regression in this codebase uses, but does not include the surrounding shell/navigation DOM.

**Builder next action:** none — stopping here. Reviewer: `account-station-transfer` is pushed at `73706c3` with both corrections and new regression evidence, ready for the independent review the Reviewer's own audit called for. Awaiting verdict.


## Independent Reviewer audit — pushed `b13b7e2` (2026-10-11)

**Verdict: Stop — architectural risk.** GitHub independently confirms topic HEAD `b13b7e2c6671f95105ee11f08e2a4328011f6549`, five commits ahead of `main=cee882c` and no main/staging changes. Read actual AccountController, AccountBrand, AccountIdentity, AccountDrawerHost, AccountMedia, Account Code Map and Settings registration, compared to main's locked StationDrawerLifecycleContract-v1 and architecture skill. Builder test passes are reported, not independently executed. Do not merge/deploy.

**Blocking differences:**
1. `AccountBrand::publish/disable/enable/settle` and AccountController's custom status mutator constitute a parallel lifecycle implementation; Account Code Map claims an Owner-approved deviation, but this workload never explicitly approved bypassing QSD's locked StationLifecycle engine or dropping Archive/Trash/Restore/Delete. The Owner expressly required Station parts to follow established Station lifecycle. Singleton permanence can be proposed, not presumed as an exemption.
2. `settings-station/register.ts` makes both global Tools and Security available under `stationIds:['services','account']`. This is additive reachability, not the requested transfer of global-only settings to Account. Owner specifically excluded Service Meta, Create Service, Create Category and AI/AOI from Account. Existing QSD Settings/Security backend/credential storage must remain untouched.
3. `AccountDrawerHost` directly composes `EntityActionFooter` and a separate editor arrangement rather than demonstrating conformity to the established canonical footer/identity-handoff/notification system. Need a precise comparison to the Service implementation; do not instantiate a second component/lifecycle contract.

**Bounded Builder correction on SAME topic branch:** Audit Service/Category against Account for lifecycle, drawer, statuses, footer, notification and identity handoff; reuse platform owners and established components wherever applicable. Provide an explicit exception proposal only for truly immutable singleton operations, with evidence and Owner decision BEFORE implementing an alternative lifecycle. Relocate global Tools/Security presentation so Services no longer offers their global panels, while Service Meta stays Services-only and security APIs/storage remain unchanged. Add mounted Preact regression for Account card/drawer and navigation/Settings route placement, plus source+PHP lifecycle transition regressions. Update Code Maps/roadmap to reflect actual implementation without invented Owner approvals. Run `npm test` and `npm run docs:check`. Do not merge/deploy/push unrelated work; stop if decision needed. Keep Account identity prefixes and fixed native binding only if they pass architecture comparison.


## Reviewer decision on correction `73706c3` — 2026-10-11

**Verdict: Stop — architectural risk.** Independently inspected pushed `AccountBrand.php`, `settings-station/register.ts`, `AccountDrawerHost.tsx`, `docs/code-map/account-station.md` against main StationDrawerLifecycleContract-v1 and QSD architecture skill. Corrected source now calls shared `StationLifecycle::statusRouteTransition` for publish and `isLive` for disable; global Tools/Security registered to Account alone, with Service fields under Services. Mounted test outcomes are Builder-reported, not independently executed; no deployed runtime was verified.

**Unresolved Owner decision:** Account is an identity-permanent singleton. Should Account omit Archive/Trash/Restore/Delete entirely, as Builder currently proposes, or use complete established Station travel with identity preserved? Do not assume singleton permanence automatically exempts a Station from locked lifecycle rules. Do not change shared canonical footer or implement either alternative until the Owner decides. Account currently assembles `EntityActionFooter` rather than the canonical footer specifically because the latter exposes Archive/Trash.

**Next action:** Owner chooses (A) explicit narrowly documented singleton exception (Save/Publish/Disable/Enable only), or (B) full lifecycle travel, with no destruction/re-minting of the singleton Platform IDs and precisely defined Archive/Trash/Restore/Delete semantics. If B cannot preserve fixed node identity, stop and bring back an architecture proposal rather than coding a destructive path. Builder remains stopped; do not merge, push main/staging, deploy or test live Rezdy secrets. Upon Owner decision, Reviewer issues bounded correction/acceptance path for same topic branch.


## Corrected active Reviewer instruction — 2026-10-11 (supersedes prior drawer transfer instruction)

**Status: BUILDER ACTION REQUIRED — FINISH EXISTING ACCOUNT WITH MINIMAL CHANGES.** Preserve QSD's established Station Manager, drawer shell, editor, footer, notification and lifecycle infrastructure. The previous instruction to **restore/transfer CompuZign's AccountDrawerContent, EntityDrawer controller, dialogs, and drawer composition** is **withdrawn**. Do not port these files, recompose the current QSD Account drawer solely for structural parity, or create another drawer system. The current QSD Account drawer already uses shared ReadBlock, InlineEditorShell and EntityActionFooter. Compare actual behaviour with CompuZign's proven Account; change it only for a demonstrable missing or failing functional contract, with a focused test. Differences in file layout alone are NOT defects. Keep the valid empty footer overflow (no Archive/Trash/Restore/Delete) and permanent singleton behaviour.

**Verified minimal identity fixes:** CompuZign AccountIdentity has reserved-after-persist recovery via the EXISTING PlatformIdentifierStation::assign(); QSD currently calls ensure() directly and can reject a stored RESERVED ID. Adapt the proven recovery narrowly through QSD's existing infrastructure, without re-minting or new identity infrastructure; test interrupted first Save and retry. CompuZign also persists/validates each child's parent_platform_id, absent in QSD; preserve parent linkage within the existing Account aggregate with safe handling of existing rows and test mismatches. No destructive migration.

**Preserve unchanged:** Current Account registration/UI unless an actual functional defect is proved; QSD Platform ID prefixes, global Tools/Security presented under Account only, Service-specific General/metafields in Services, existing QSD security/credential broker and REST compatibility. No new stages, Station redesign, Archive/Trash choice, or live Rezdy tests. Reuse all seven existing Account commits as baseline.

**Builder action, one bounded completion batch:** Review current QSD files first; implement only the two proven identity gaps and any clearly evidenced Account UI behavioural defect. Add focused tests, run `npm test` and `npm run docs:check`, update Code Map only for actual behaviour, push only `account-station-transfer`, record exact SHA and evidence here, then STOP for Reviewer. Do not merge, deploy or push main/staging. In any uncertainty, preserve existing working implementation and report the precise gap rather than expanding work.

Earlier audit entries remain historical; **this section is the sole active instruction**.

## Builder completion handoff (2026-10-11)

Bounded completion executed on the same topic branch, one new commit, baseline unchanged (all seven prior commits kept as-is — no drawer file touched, no port of CompuZign's `AccountDrawerContent`/`EntityDrawer` attempted):

| Full SHA | Commit |
|---|---|
| `29503aecaf262265b379e14ca8c16485466dd088` | `account: finish minimal identity completion — reserved-recovery and parent linkage` (branch HEAD) |

**Verified remote state:** `origin/account-station-transfer` HEAD = `29503aecaf262265b379e14ca8c16485466dd088` (fast-forward push from `73706c3`, confirmed via `git rev-parse origin/account-station-transfer` after push). No `main`/`staging` push, no merge.

**Identity gap 1 (reserved-after-persist recovery) — fixed.** Compared QSD's `AccountIdentity.php` directly against the pinned CompuZign reference (`fe2e571f` at `src/Modules/Account/Support/AccountIdentity.php`, read-only). QSD's `bootstrap()` called `PlatformIdentifierStation::ensure()` directly; `ensure()` throws when a node's native storage already holds an id whose registry forward record is still `reserved` rather than `bound` — exactly the state left behind by a request that died inside `PlatformIdentifierStation::assign()` between its native-storage write and its registry bound-flip. Fixed by adding a check before `ensure()`: if the stored id resolves to a `reserved` record, finish the bind with the Station's own `assign()` (re-validating the same reservation, read-back verified, no new id minted) before falling through to `ensure()`'s normal path. This is the identical pattern CompuZign's own `ensureNode()` uses — no new identity infrastructure invented.

**Identity gap 2 (parent_platform_id) — fixed.** CompuZign persists and validates each node's `parent_platform_id`; QSD did not persist it at all. Added `parent_platform_id` to `AccountSchema::defaultState()`'s node shape (default `null`), extended `AccountRepository::writeNodePlatformId()` to accept and store it (written once, alongside `platform_id`, only on first bind — identity and parent are set together or not at all), and added `AccountRepository::readNodeParentPlatformId()`. `AccountIdentity::bootstrap()` now threads the previous node's bound id as `$expectedParent` down the chain and, after `ensure()`/`assign()` resolves a node, compares its *stored* parent against that expectation: a genuine mismatch throws `PlatformIdentifierConflict`; a node with no parent recorded at all (pre-existing row from before this fix — "safe handling of existing rows" as instructed) is read as unrecorded, not as a mismatch, and bootstrap proceeds normally. No destructive migration — nothing is rewritten for already-bound nodes.

**Account UI behavioural defect — none found, none changed.** Re-read `AccountDrawerHost.tsx`, `AccountCard.tsx`, `useAccountStation.ts`, `AccountSettingsSection.tsx` against the withdrawn instruction's premise; found no demonstrable missing/failing functional contract versus CompuZign beyond the two identity gaps above (CompuZign's extra Archive/Trash-adjacent surface area is the already-resolved singleton-carve-out question, not a defect). File-layout differences were treated as differences, not defects, per this section's own instruction. No drawer file touched.

**New/changed tests — `tests/account-station.php`:** three new scenarios (26 total assertions, up from 19): (6) a node reserved-but-not-yet-bound (simulated by reserving directly through the Station and writing only to native storage, bypassing `assign()`) is completed by `bootstrap()` in place, confirmed bound afterward, never re-minted; (7) a node whose stored `parent_platform_id` is corrupted independently of the registry causes `bootstrap()` to throw `PlatformIdentifierConflict` rather than silently re-chaining; (8) a legacy row built via `unset()` of the `parent_platform_id` key entirely (simulating a site bootstrapped before this fix) reads its parent as `null`, not a mismatch, and bootstraps cleanly.

**Validation at `29503ae`:** `npm test` green in full — typecheck; every PHP test including `tests/account-station.php` now at 26 assertions; build; JS **27/27** unchanged (no frontend/regression file touched by this commit); `docs:check` (49 Markdown files, 20 Code Maps — `docs/code-map/account-station.md` and `src/Modules/Account/CLAUDE.md` updated for the two actual behaviour changes only, re-trimmed under the 600-word cap).

**Not done / explicitly out of scope, per this section's own bound:** no push of `main`/`staging`; no merge; no deploy; no live Rezdy/WordPress validation (still deferred, never described as passed); no Settings/Security, StationLifecycle, drawer, or footer change (all already corrected in the prior cycle and untouched here); the Archive/Trash/Restore/Delete exception remains exactly as settled by this section — omitted, as a kept singleton carve-out, not re-opened. The repository-coordination "Cross-chat handover" Google Doc update was not attempted: the Google Drive connector is unauthenticated in this session's execution surface, so that continuity step is recorded here as unavailable rather than silently skipped.

**Builder next action:** none — stopping here. Reviewer: `account-station-transfer` is pushed at `29503ae` with both identity gaps closed and three new regression assertions; current baseline unchanged otherwise. Awaiting verdict.


## Reviewer verdict on minimal identity completion — 2026-10-11

**Proceed with safeguards.** Independently verified remote `account-station-transfer=29503aecaf262265b379e14ca8c16485466dd088`; `main` and `staging` remain `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`. GitHub comparison from `73706c3` shows exactly one new Account identity correction commit, six files (AccountIdentity, AccountRepository, AccountSchema, account-station test, Code Map, local CLAUDE.md); no drawer, Settings, Service, security backend or deployment files changed. Source inspection confirms original CompuZign-style reserved-to-bound recovery via shared PlatformIdentifierStation::assign and parent ID persistence/mismatch checking. Current Account drawer and permanent singleton travel are preserved. Builder reports full `npm test` and `npm run docs:check` green; reviewer has not independently executed tests or checked authenticated runtime.

**Bounded next Builder action:** Recheck current remote heads, fast-forward ancestry/changed-file scope, and current CI for Account candidate; prepare source-only integration into `main` from the unchanged topic branch, with exact GitHub comparison and test evidence. If repository governance allows a non-force fast-forward and all required checks are green, perform the authorised source-only integration without merging into `staging`, triggering production deploy, changing workflows or touching credentials. Verify exact remote SHA and report; retire topic branch only after integration is confirmed and governance permits. If unexpected CI failure, divergence, branch protections or changed scope, STOP and report without workaround. **Do not deploy to staging in this action**; authenticated browser and deferred Rezdy validations are separate gates. No further drawer transfer, lifecycle redesign or new phases.

## Builder integration handoff (2026-10-11)

**Preconditions checked before touching `main`:** `main`/`staging` both still `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` (unmoved since the batch began); `git merge-base --is-ancestor` confirmed `main` is a strict ancestor of `account-station-transfer` (7 commits ahead, no divergence) — a plain fast-forward, no three-way merge. Remote branch count was exactly the governed maximum of four (`main`, `staging`, `Project-work-instructions`, `account-station-transfer`). `.github/workflows/ci-deploy.yml` confirmed read-only: `deploy-staging` only runs `if: github.ref == 'refs/heads/staging' || workflow_dispatch`; a push to `main` only runs the `test` job, so integration could not trigger a deploy. No separate CI run existed yet for the topic branch itself (the workflow's `push` trigger only fires on `main`/`staging`; no PR was opened), so the only pre-push test evidence available was local `npm test`/`docs:check`, already green at `29503ae` from the prior handoff.

**Action taken:** fast-forward merge only — `git checkout main && git merge --ff-only origin/account-station-transfer && git push origin main:main`. No force-push, no history rewrite, no `staging` push, no workflow or credential change. This is a hard-to-reverse, shared-state action, so it was executed only after the Owner explicitly confirmed it in-session (`AskUserQuestion`), separately from the original per-phase push authorisation.

**One local test anomaly investigated before pushing, not worked around:** a full local `npm test` run on the merged `main` hit one failure, `tests/settings-connections.php`: "a tampered ciphertext does not open." This file is Settings/Connections credential-broker test coverage, completely untouched by this Account batch. Root-caused, not assumed: the test's tamper fixture is `$tampered['ct'] = base64_encode(substr(base64_decode($ct), 0, -1) . 'x')` — it replaces the ciphertext's last byte with the literal byte `0x78`. Whenever that byte already equals `0x78` by chance (the nonce, and therefore the ciphertext, is random per run), the "tamper" is a no-op, the envelope is still genuinely valid, and `open()` correctly returns the real plaintext instead of `null` — a ~1-in-256 false-negative in the test's own fixture construction, not a defect in the actual XChaCha20-Poly1305 AEAD tag check, and not caused by this batch. Confirmed via 10 subsequent clean reruns (5 of `tests/settings-connections.php` alone, 5 of the full `npm run test:php` loop) that the failure does not reproduce and `main`'s current state is green. Reported to the Owner before pushing; proceeding was an explicit Owner decision, not a unilateral Builder workaround. This pre-existing test-fixture flake is recorded here as a separate, unrelated finding for whoever next touches `tests/settings-connections.php` — fixing it is out of this batch's scope and was not attempted.

**Verified remote state after push:** `origin/main` HEAD = `29503aecaf262265b379e14ca8c16485466dd088` (exactly `account-station-transfer`'s HEAD, confirmed via `git rev-parse origin/main` after push). `origin/staging` unchanged at `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`. GitHub Actions run [`38105065818`](https://github.com/CodeByNath/qldscubadive/actions/runs/38105065818) on the new `main` SHA completed: `test` job = `success`, `deploy-staging` job = `skipped` (correctly, since the push was to `main` not `staging`) — independent confirmation, not Builder-reported, that no deploy occurred and CI is green on the integrated candidate.

**Topic branch `account-station-transfer`:** not yet deleted. It now points at the same commit as `main` (`29503ae`), so retiring it is safe and matches governance's normal accepted-work path, but deletion was held back pending the same explicit confirmation pattern used for the `main` push, rather than bundling it into the same approval.

**Not done:** no push to `staging`; no deploy; no credential/workflow change; no live Rezdy/WordPress validation (still deferred); no further drawer, lifecycle, or Settings change — none were in scope for this action.

**Builder next action:** none — stopping here. Reviewer: `main` now carries the full Account Station transfer batch at `29503ae`, independently confirmed via GitHub Actions. `staging` is untouched. The topic branch is redundant but not yet deleted. Awaiting verdict on whether to retire it and close this batch, or further instruction.
