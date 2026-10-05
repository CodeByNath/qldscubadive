# Phase 6 completion bundle — Trash confirmation + lifecycle regression closeout

Status: BUILDER ACTION REQUIRED
Phase: Phase 6 remainder (6.5 + 6.6)
Actor: Builder

## Goal

Complete the remaining authorised Phase 6 lifecycle work in one Builder package instead of splitting it into multiple tiny review cycles.

This package may finish:
- Phase 6.5 — saved-Service Trash confirmation;
- Phase 6.6 — lifecycle regression/contract closeout and documentation alignment.

Do not start post-Phase-6 scuba-domain features.

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md` — Phase 6 items 5–6
4. `docs/architecture/StationDrawerLifecycleContract-v1.md` — especially §§5, 7, 11
5. `docs/code-map/drawer-system.md`
6. `docs/code-map/lifecycle-system.md`
7. `docs/code-map/service-station.md`
8. relevant Service/Category drawer controllers, dialogs, lifecycle hooks and existing regressions

## Existing source facts

- Category already routes Move to Trash through `CategoryDrawerDialogs` confirmation.
- Service saved-record Trash currently reaches `lifecycle.handleTrash` directly from the footer/controller with no equivalent saved-record confirmation.
- Service already owns `ServiceDrawerDialogs`; use that convention rather than adding a second dialog system.
- Phase 6.1–6.4 are accepted on `main`.
- Phase 6.4 deliberately leaves one documented known gap: Publish is still two requests, so activation failure after successful settle can leave settled/non-active state. Do not redesign that here.

## Required outcome

### A. Saved Service Trash confirmation

For an existing persisted Service:
1. choosing Move to Trash must arm/open a confirmation dialog;
2. Cancel performs no mutation;
3. Confirm invokes the existing Service Station Trash action exactly once;
4. successful Trash follows the existing terminal close path;
5. failure must not fake success/close;
6. local `new` discard/Trash behaviour remains the existing local-authoring flow and must not be converted into a server mutation.

Use `ServiceDrawerDialogs` + controller state/handlers. Do not let presentation call endpoints.

### B. Phase 6 regression closeout

Add/extend mounted/contract coverage so Phase 6 has deterministic evidence for the accepted lifecycle behavior, including at minimum:

- saved Service Move to Trash requires confirmation and fires exactly once only after confirm;
- Cancel sends no Trash request;
- Category's existing destructive confirmation remains intact;
- Restore still lands unmasked Pending and never auto-selects Publish/Active, Disable, or Enable;
- Publish settle-failure guard remains covered;
- server-side illegal-transition protection remains covered;
- Bin and reachable-Category regressions remain mounted in `npm test`;
- extend `drawer-module-entry` only where needed to pin lifecycle/dialog conformance rather than duplicating behavior tests.

### C. Documentation closeout

After source/tests match:
- update `docs/roadmap.md` to mark Phase 6.4 accepted and 6.5/6.6 completed;
- update `docs/code-map/lifecycle-system.md`, `drawer-system.md`, and Service map only where implementation changed;
- preserve the honest two-request Publish known gap unless separately resolved later;
- do not create Project History without Owner approval.

## Builder execution model

This is one larger work package. The Builder may complete all authorised items above on one topic branch before handing back for review.

Do **not** stop between Trash confirmation, regression additions, and documentation updates unless:
- repository authority conflicts;
- the work requires a new architecture decision;
- scope would cross into post-Phase-6 domain work;
- tests expose an unrelated architecture defect.

## Validation

From `wp-content/plugins/qsd-platform/` run:

- `npm test`
- `npm run docs:check`

Also run any focused new/changed regression directly and report it.

## Hard exclusions

- No new Publish endpoint or transaction redesign.
- No new lifecycle vocabulary.
- No Restore/Disable/Enable semantic changes.
- No identity/storage/API-family redesign.
- No Service Details, Courses/Trips, Dive Sites, Equipment, Staff, public read API, Settings/Rezdy, or other post-Phase-6 feature.
- No staging or production deployment.

## Handoff

Push one exact topic SHA, update this same file to `AWAITING REVIEWER REVIEW`, and report:
- changed files;
- Trash confirm request-flow evidence;
- Phase 6 regression/contract evidence;
- `npm test`;
- `npm run docs:check`;
- explicit confirmation no post-Phase-6 or deployment work was included.

## Builder handoff

**Candidate:** `phase-6-completion/trash-confirm-closeout` @ `7b099dac5e54a180540dcde29cd7f1ea439614fd` — one commit on accepted `main` `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f`.

### Changed files

- `resources/ts/service-station/drawer/useServiceDrawerController.ts` — `trashConfirm`/`trashError` state; footer `handleTrash` is now `requestTrash` (a persisted Service arms the dialog and sends nothing; `station.isNew` keeps the existing local discard-by-close); `cancelTrash`; `handleConfirmTrash` runs the lifecycle Trash once (in-flight ref guard against double click), closes the dialog only on success, otherwise shows an error. Handlers read the latest identity/lifecycle through a ref because the footer VNode is re-registered only on its gating inputs.
- `resources/ts/service-station/drawer/useServiceLifecycle.ts` — `handleTrash` now resolves `true` only when the record left the surface (no behaviour change otherwise; success still closes via `closeBypassingGuard`).
- `resources/ts/service-station/drawer/ServiceDrawerDialogs.tsx` — new "Move {title} to Trash?" dialog (`cz-publish-confirm*`, click-outside cancels, `role="alert"` error, buttons disabled while the Station is busy). Presentation calls only controller handlers.
- `scripts/drawer-trash-confirm-regression.mjs` (new) + `package.json` `regression:drawer-trash-confirm`.
- `scripts/drawer-module-entry-contract.ts` — pins Service `handleTrash: requestTrash` + dialog confirm wiring and Category `setConfirmDialog('trash')` + `handleConfirmDestructive` wiring.
- Docs: `docs/roadmap.md` (6.4 accepted; 6.5/6.6 done, awaiting acceptance), `docs/code-map/lifecycle-system.md`, `drawer-system.md`, `service-station.md` (one sentence each), lifecycle contract §8 conformance bullet. Two-request Publish known gap preserved unchanged.

### Trash confirm request-flow evidence

`node scripts/drawer-trash-confirm-regression.mjs` — mounts the real `ServiceDrawerHost` and `CategoryDrawerHost`, fetch mock records every mutation. 26/26 checks pass:

- Service footer Move to Trash → dialog open, 0 requests.
- Cancel → dialog closed, 0 requests, drawer not closed.
- Confirm (clicked twice) → exactly 1 `POST admin/services/911/status {"platform_status":"trashed"}`, drawer closed once; no Restore/Disable/Enable.
- Trash 422 → 1 attempt, dialog stays open with alert, drawer not closed, record unchanged.
- Local `new` → no dialog, drawer closed, 0 requests.
- Category → arm sends 0; Cancel sends 0; Confirm sends exactly 1 `PATCH …/status {"platform_status":"trashed"}`, drawer closed once.

Against the pre-fix source (source stashed, script kept) it fails: arming immediately sent the Trash write, Cancel was unreachable, and two Trash writes/closes occurred.

### Phase 6 regression/contract evidence (all in `npm test`)

- Trash confirmation (Service + Category): `regression:drawer-trash-confirm` (new); wiring pinned in `contract:drawer-module-entry`.
- Restore → unmasked Pending, no `/status` write, never Publish/Disable/Enable: `regression:service-home-bin` (unchanged) and PHP `tests/service-lifecycle-mask.php`, `tests/category-pending-lifecycle.php`.
- Publish settle-failure guard: `regression:publish-activation-guard` (unchanged).
- Server-side illegal-transition 422s: PHP `tests/service-lifecycle-mask.php`, `tests/category-pending-lifecycle.php` (unchanged, run by `npm test`).
- Bin and reachable-Category: `regression:service-home-bin`, `regression:service-home-connections`, `contract:service-home-connections` remain declared and run by `run-all.mjs`.

### Checks (from `wp-content/plugins/qsd-platform/`)

- `npm test` — exit 0 (typecheck, PHP tests, build, 22/22 JS contracts/regressions/snapshots, docs:check).
- `npm run docs:check` — passed (41 Markdown files, 18 Code Maps).
- Focused: `npm run regression:drawer-trash-confirm` passed; `npx tsx scripts/drawer-module-entry-contract.ts` passed.

### Notes for Reviewer

- The "Before you leave" exit prompt's Move to Trash (`useServiceExitFlow.handleNewSvcTrash`) is itself a confirmation dialog and was left unchanged.
- File sizes: `useServiceDrawerController.ts` 269 lines, `ServiceDrawerDialogs.tsx` 212, new regression 271 — all under 600.

### Exclusions confirmed

No new Publish endpoint or transaction change; no lifecycle vocabulary, Restore/Disable/Enable, identity/storage/API changes; no backend change; no post-Phase-6 feature; no staging or production deployment. Project History not created.


## Reviewer decision

Verdict: Proceed

Independent review of candidate `7b099dac5e54a180540dcde29cd7f1ea439614fd` confirms the Phase 6 completion bundle is correctly bounded and satisfies the authorised 6.5 + 6.6 scope:

- the topic branch is exactly one commit ahead of accepted `main` `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f`;
- saved Service Move to Trash now arms a `ServiceDrawerDialogs` confirmation instead of mutating immediately;
- Cancel performs no mutation and keeps the drawer open;
- Confirm is guarded against double-submit, invokes the existing Station Trash action exactly once, and closes only after authoritative success;
- Trash failure keeps the Service dialog open with an error and does not fake a terminal close;
- local `new` discard remains local close-only behavior with no server write;
- Category's existing destructive dialog path remains intact;
- presentation owns no endpoint call; the controller coordinates the dialog and the Station remains the write boundary;
- `drawer-trash-confirm-regression.mjs` mounts the real Service and Category drawer hosts and proves arm/cancel/confirm/failure/new-record behavior;
- `drawer-module-entry` pins both entities' confirmation wiring;
- the new regression is declared in `package.json`, and `run-all.mjs` automatically includes every declared regression/contract/snapshot in `npm test`;
- existing Restore-to-unmasked-Pending, Publish settle-failure, server illegal-transition, Bin, and reachable-Category coverage remains part of the full suite;
- lifecycle/drawer/Service docs and the locked lifecycle contract were updated only to describe the implemented confirmation behavior;
- the two-request Publish known gap remains honestly documented;
- no backend lifecycle, identity/storage/API, post-Phase-6 feature, Project History, staging, or production deployment work entered scope.

Builder reports `npm test` exit 0, `npm run docs:check` pass, focused Trash regression pass, and drawer-module-entry contract pass. No GitHub Actions run exists for the topic SHA before promotion, as expected.

No source correction is required.

## Next Builder action — promote the Phase 6 completion bundle

1. Promote exact accepted topic head `7b099dac5e54a180540dcde29cd7f1ea439614fd` from `phase-6-completion/trash-confirm-closeout` to `main` without changing scope.
2. Verify resulting `main` is that exact accepted candidate.
3. Verify post-push `main` CI completes successfully.
4. Do not deploy to `staging` or production.
5. After successful main/CI verification, delete only `phase-6-completion/trash-confirm-closeout`; preserve the three permanent branches.
6. Verify final remote branches are exactly `main`, `staging`, and `Project-work-instructions`.
7. Update this same work file with main SHA, CI run/result, final branch list, confirmation no deployment occurred, set `Status: AWAITING REVIEWER REVIEW`, set `Actor: Reviewer`, and stop.
8. Do not begin post-Phase-6 scuba-domain work until Reviewer closes this bundle.
