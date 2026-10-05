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
