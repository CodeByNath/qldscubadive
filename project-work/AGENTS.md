# QSD Project Work Protocol

Before any controlled project work:

1. Check and sync the `Project-work-instructions` branch first.
2. Read `project-work/AGENTS.md`.
3. Read `project-work/PROJECT-RULES.md` when architecture, authority, repository governance, deployment, or identity is relevant.
4. Read the single active work file and follow its current Status, Phase, actor, scope, exclusions, evidence requirements, and next action literally.
5. Read the relevant authoritative `main` documentation and implementation before acting.
6. Determine whether BUILDER or REVIEWER owns the next action.
7. Work only on the active phase.
8. Builder implements; Reviewer independently audits.
9. Do not advance until the current phase is accepted or explicitly deferred by the Reviewer.
10. Never treat `Project-work-instructions` as QSD product architecture authority.

## Mandatory remote-authority preflight (stale-checkout prevention)

On EVERY cycle invocation, before concluding there is no Builder work, **do not trust the currently checked-out work file**, a local `Project-work-instructions` branch, a saved handover, or a prior chat report.

1. Verify `origin` is exactly `CodeByNath/qldscubadive`. Run `git fetch origin +refs/heads/Project-work-instructions:refs/remotes/origin/Project-work-instructions`; abort if fetch fails.
2. Capture `git rev-parse origin/Project-work-instructions` and read `project-work/AGENTS.md`, `project-work/PROJECT-RULES.md`, and the active file **from that exact remote-tracking commit** using `git show <sha>:<path>`. Reading working-tree files or `git show Project-work-instructions:<path>` does not satisfy this gate.
3. Print the confirmed repository, exact remote instruction SHA, active work-file path, Phase, Status and Actor before making a role decision. If local and remote status differ, **remote wins**; report local divergence, but never claim there is no work from the local status.
4. If the ref cannot be fetched/read, report `REMOTE COORDINATION UNVERIFIED` with the concrete Git error and stop. Never silently fall back to cached local instructions.
5. For an authorised coordination edit, start from the fetched head, use non-force update, and verify remote SHA after push. Never overwrite concurrent Reviewer/Builder updates.

This preflight is workflow-only: it does not authorise source edits, additional branches, cross-repository access, production deployment, or role changes.

## Mandatory QSD platform/host distinction (Owner correction, 17 October 2026)

Before describing QSD Studio or asking the Owner to validate it, read QSD `main` `AGENTS.md`, `docs/ai-index.md`, the current `docs/code-map/studio-host.md` on the **deployed staging ref**, and relevant source. The QSD Platform has its **own Admin Station** (`/station/`) and a separate WEX Studio Admin Dashboard (`/studio/`); these are QSD/WEX-owned user interfaces, **not WordPress admin screens**. WordPress is a behind-the-scenes runtime/storage/route host, not the system the Owner administers through. Never direct the Owner into wp-admin or portray a host `wp-config.php` setting as an ordinary QSD admin-UI step. Staging's `staging.<domain>` subdomain is the deployed QSD staging site, not a separate WordPress *product* or proof of `wp_get_environment_type() === 'staging'`.

For Phase 1I runtime validation, Reviewer must first inspect deployment workflow and available hosting/environment evidence and seek an authorised **platform-level or server-side diagnostic** for the release gate; ask Owner for a hosting-level check only if truly indispensable, with clear technical explanation and no exposure of credentials. Separate host configuration facts, deployed source, and visible dashboard behaviour; a dashboard screenshot cannot prove pointer activation. Do not silently change environment configuration, business UI, or production. This is a workflow reminder of existing `main` architecture, not new product authority.

## Cycle trigger meaning

For this repository, `run the cycle`, `continue the work`, `review the latest work`, `check the builder`, or equivalent mean the QSD Builder/Reviewer project cycle, not an npm test command.

On those triggers, first fetch/read `origin/Project-work-instructions`, identify the single active work file and its Status/Phase, determine the current actor, and execute only the authorised next action. Validation is a phase step only when required.

## Chat reporting

The work file is the channel. All trace detail, evidence, history, and reasoning go there, never into chat.

When a cycle action completes (commit, push, merge, promotion, handoff, or a stop-with-evidence), the chat reply is one line: who holds the next action, per the active work file's `Actor:` field, after that update is pushed. Nothing else — no summary of findings, no restated evidence, no narration of steps taken.

**Exception:** report in chat immediately and in full when something blocks completing the push/merge/handoff itself — a failing test, a rejected or diverged push, a merge conflict, CI failure, or a missing capability that stops the authorised action from landing. That is actionable right now by whoever is reading chat, and must not wait silently in the work file alone.

## Executor capability preflight

Role authority and executor capability are separate checks.

Before executing a phase:

1. Enumerate required operations, including branch creation/deletion, commit/push, merge, CI inspection, browser/runtime validation, deployment inspection, file writes, Google Drive handover edits, or external actions.
2. Verify the current execution surface can complete every mandatory operation.
3. Do not partially advance a phase that cannot be completed end-to-end.
4. Do not reinterpret a missing tool as a project decision, architecture blocker, or failed implementation.
5. If a required capability is unavailable, keep ownership with the authorised role and record the required execution surface.
6. Before opening a topic branch, inspect remote heads and obey `docs/foundation/repository-governance.md`.

## Git authorization during a cycle

A defined cycle trigger authorizes non-destructive Git operations required by the currently authorised phase against `CodeByNath/qldscubadive` only.

Before any push, verify origin/target repository is exactly `CodeByNath/qldscubadive`.

Builder authorization may include, when required by the active work file:

- fetch and remote-state inspection;
- fast-forward-only sync;
- create/switch to the authorised topic branch;
- commit only authorised changes;
- push the authorised topic branch;
- verify the exact remote SHA;
- update the same active work file on `Project-work-instructions`;
- push that coordination update and verify its remote state.

This does not authorize force-push, history rewriting, deleting permanent branches, changing remotes, production deployment, widening deployment paths, unrelated work, or publishing secrets.

## Roles

Roles are governance roles, not model/vendor names.

**Builder**
- May edit only when the active work file assigns Builder action or the Owner explicitly grants Builder authority.
- Must obey scope, exclusions, architecture gates, deployment boundaries, and required evidence.
- Must not self-approve, self-advance, widen scope, merge to `main` unless assigned, or begin unrelated work.

**Reviewer**
- Independently inspects actual pushed source, diff, tests, CI/runtime evidence, and repository authority.
- Builder reports are pointers, not proof.
- Only Reviewer may approve/refuse submitted work, assign corrections/next Builder work, change active phase/status, or mark work accepted/deferred.
- Reviewer does not implement the Builder's correction while acting as Reviewer.

## Builder source-push handoff

At the authorised phase boundary, Builder must:

1. run the checks required by the active work file;
2. commit the authorised work on the approved topic branch;
3. push the topic branch to origin;
4. verify the remote branch contains the pushed commit;
5. update the SAME active work file on `Project-work-instructions` to `Status: AWAITING REVIEWER REVIEW` with exact branch/SHA/evidence;
6. report changed files, checks, limitations/deviations, and unresolved issues;
7. stop for Reviewer.

A local commit, local test pass, browser check, or Builder summary is not a completed handoff.

## Reviewer handoff decision

When status is `AWAITING REVIEWER REVIEW`, Reviewer independently inspects the pushed candidate and records exactly one verdict in the SAME active work file:

- `Proceed`
- `Proceed with safeguards`
- `Stop — architectural risk`

If corrections are required, record the bounded Builder instruction there and set `BUILDER ACTION REQUIRED`. If accepted, record the next authorised state/phase. A review completed only in chat does not change project state.

## Validation and deployment evidence

Never conflate local, pushed, CI, staging-deployed, and live-runtime states.

For QSD full validation, follow current `main` authority. At present, from `wp-content/plugins/qsd-platform/`:

- `npm test`
- `npm run docs:check`

Browser, PHP/WordPress runtime, deployment, staging, or production claims require actual evidence for that boundary. `staging` is a staging deployment branch; production deployment is never implied.

## Work-file discipline

- Keep one work area in one active work file until accepted or deferred.
- Only one topic/work branch may be active at a time.
- After accepted work is promoted and independently verified, remove the completed topic branch before opening another.
- Never delete `main`, `staging`, or `Project-work-instructions` as normal housekeeping.
- For a demonstrated subsystem, begin source investigation at its Code Map.
- Apply `docs/foundation/repository-governance.md` for file limits and branch capacity.
- Keep active work files concise, normally under roughly 600 words.
- Product rules come from authoritative QSD `main`, not from this coordination branch or another repository.

## Status vocabulary

- `BUILDER ACTION REQUIRED`
- `AWAITING REVIEWER REVIEW`
- `BLOCKED — DECISION REQUIRED`
- `ACCEPTED`
- `DEFERRED`

## Cross-chat handover

A rolling Google Drive handover is maintained for continuity only.

Location:
- Drive folder: `QLD-ScubaDive / Chat Handover`
- Google Doc: `QLD-ScubaDive Chat Handover`
- Root folder: https://drive.google.com/drive/folders/1hWCmonQIaoJy1Eedhg8YD5F790TF4vxt
- Handover folder: https://drive.google.com/drive/folders/1-S6e_HSvnr7bG4Io7oE9BBeIDWNdrfk1
- Document: https://docs.google.com/document/d/1Zj0j1rnzGHozvVviY4InYqxO60fxSOYjzBWRbIx7_oQ/edit

Rules:
- The handover is continuity context, never product or workflow authority.
- At a clean chat start, read the handover, then run the normal repository cycle against GitHub authority.
- Update the same document after material project-state changes or before intentionally moving to a fresh chat.
- Do not create a new handover document for each chat.

## Owner-only override and agent role lock (12 October 2026)

- **Only the human Owner may override** an accepted manual workflow, phase boundary, hard exclusion, architecture decision gate, design token, repository boundary, permission restriction, or deployment/security safeguard. An AI agent (including Claude Code, Codex, ChatGPT or their subagents) must not grant itself an override, infer one from convenience, or treat a Builder report as approval.
- The Owner must explicitly identify the specific exception and scope. Record it in the same active work file before acting; changes to product authority require the appropriate reviewed `main` authority/ADR, not merely a coordination edit. Silence, broad access, a successful test, or an earlier permission does not count.
- **Frontend/Backend assignment:** Codex + ChatGPT handle frontend inspection/composition and visual review only when assigned; Claude Code handles QSD backend/API/adapter work only when assigned. Assignments do not supersede the Builder/Reviewer cycle or grant cross-repository writes.
- **WEX presentation exclusive:** no inline CSS, style attributes or runtime style injection; no agent-invented colours, spacing, dimensions, breakpoints, or variants. Use only values and behaviours registered by authoritative WEX source. Any missing value is an Owner/architecture gate, not permission to improvise.
- **Dynamic slots:** Header/shell owns allocation and approved named-slot composition; mounted components own internals. Use verified WEX identity/composition contracts, not fabricated IDs, root DOM/CSS traversal or client-side identity read/modify/write. QSD owns branding/record authority; WEX owns UI presentation. Distinct Header allocations may bind distinct Logo instances via approved contracts.
- **First Logo plug:** no new UI, component, CSS or visual change. Audit actual QSD branding API, WEX Header/Logo slot identity and accepted identity adapter first. QSD's existing blob GET/PUT is not the required atomic WEX adapter; never bypass it. Stop for Owner/Reviewer architecture resolution if an essential contract is missing.

## Builder specialisations (one project cycle)

The existing `run the cycle` sequence and independent Reviewer are unchanged. Every active workstream identifies `Builder: BUILDER`, `Builder: CODER`, or `Builder: DESIGNER` (equivalent actor labels `BUILDER`, `BUILDER:CODER`, `BUILDER:DESIGNER`). These are **identifiers within the existing Builder role**, not separate cycle governance or independent approval authorities.

- `BUILDER`: existing general assigned implementation; do not guess its specialization.
- `BUILDER:CODER`: Claude Code or Owner-designated coder; assigned backend/data/API/adapter/persistence and its tests, **only within authorised scope**.
- `BUILDER:DESIGNER`: Codex/ChatGPT or Owner-designated designer; assigned UI/UX, frontend composition, interaction, visual testing and WEX-conformant presentation, **only within authorised scope**.
- `REVIEWER`: unchanged independent acceptance. No self-review and no Builder-to-Builder approval.

Parallel CODER and DESIGNER work is permitted only where the Owner/work instructions give distinct, non-overlapping scopes, file ownership, and branch/handover plans within existing capacity. Same feature may have distinct bounded work areas; do not add a competing approval system or overwrite the other agent's work. Shared contract work requires a defined interface, not a speculative architecture gate. One agent cannot block its independent producer-side task merely because another agent's consumer cannot render yet. Example: Profile ID -> QSD logo data -> stop; WEX independently handles Logo rendering/fallback. An actual missing contract required to complete the agent's **own assigned operation** is a stop condition.

Only Owner can authorise manual exceptions or scope overrides, explicitly and specifically. Every agent reads root `AGENTS.md`, `Project-work-instructions/project-work/AGENTS.md`, current active file, and the **existing platform-architecture skill** when an architecture decision is relevant. Platform skills explain **domain architecture**, not new work-cycle rules; `run the cycle` uses the single WeeraX Project Cycle procedure. Product authority stays in each repo, never transplanted from CompuZign.
