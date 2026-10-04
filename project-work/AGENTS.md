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

## Cycle trigger meaning

For this repository, `run the cycle`, `continue the work`, `review the latest work`, `check the builder`, or equivalent mean the QSD Builder/Reviewer project cycle, not an npm test command.

On those triggers, first fetch/read `origin/Project-work-instructions`, identify the single active work file and its Status/Phase, determine the current actor, and execute only the authorised next action. Validation is a phase step only when required.

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
