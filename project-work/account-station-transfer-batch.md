# Account Station transfer — approved batch (queued)
Status: QUEUED — AWAIT SECURITY PHASE 2 CLOSEOUT
Actor when released: Builder (VS Code)
Target: CodeByNath/qldscubadive only
Source reference READ ONLY: CodeByNath/compuzign-platform at fe2e571f1bcff264bd1447e3a35bbdc5450abed3
Owner authorization: One continuous local workload, one independently testable local commit per phase; one final Reviewer handoff. No merge, staging, production, or destructive migration authorized.

## Release gate
The currently active Security Phase 2 work in `security-phase-2-runtime-validation.md` retains priority and the sole topic branch `docs/settings-security-roadmap`. Do not start this batch until Phase 2 is accepted/deferred by Reviewer, existing topic promoted/retired under governance, and a single new topic branch is available. No new work branch while the existing one remains. Obtain current QSD main/CI truth, not stale source assumptions.

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
- Security Phase 2 safeguards must remain proven. Test old endpoint compatibility, credentials, permission gates and route accessibility. Commit `account: phase D global settings placement`.

## Phase E — validation, handover and closeout
- Update docs/roadmap.md (authoritative handover/current state), relevant Code Maps, AI index, local instructions and architecture wording with exact implemented behavior. No extra handover. Keep files cohesive and normal <=600 lines.
- Run focused tests after each phase and from plugin root run `npm test` and `npm run docs:check` once at final candidate. Record exact phase commit SHAs/diffs, changed files, checks and remaining uncertainty.
- Commit `account: phase E documentation validation` only when all safeguards pass.
- **Stop before pushing**: report final local HEAD and commit chain to Reviewer/Owner for authorization; DO NOT push, merge, deploy or self-approve. This explicit Owner batch boundary supersedes the ordinary per-phase push handoff only for this workload; no staging or production change.

## Hard stop / rollback
At ANY phase if implementation would compromise identity permanence, ownership, credential security, lifecycle, persisted data, existing Service/Category behavior, or require destructive migration or deployment expansion: stop before that phase's commit and before any push, retain previously valid local commits, record evidence + exact blocking decision, ask Owner/Reviewer. Never silently workaround. Never continue into next phase with failing tests. Never copy full plugin or source-specific Platform IDs. Only QSD repository is writable.
