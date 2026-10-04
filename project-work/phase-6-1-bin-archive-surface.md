# Phase 6.1 — Bin / Archive Surface

Status: BUILDER ACTION REQUIRED
Phase: Phase 6.1 — Bin / Archive surface

## Authority

Read in this order before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md` — Phase 6.1
4. `docs/code-map/lifecycle-system.md`
5. `docs/code-map/service-station.md`
6. `docs/code-map/categories.md`
7. `docs/architecture/StationDrawerLifecycleContract-v1.md`
8. `skills/qsd-platform-architecture/SKILL.md`
9. authoritative source reached from those maps

## Owner-locked outcome

Do not redesign lifecycle. Plug a simple generic Bin/Archive surface into the existing Service and Category lifecycle only.

Every listed record shows:
- label/name;
- permanent Platform ID.

Archived records:
- Restore;
- one split action for the existing legal travel/delete actions requested by the Owner: Move to Bin / Delete.

Bin/Trash records:
- Restore;
- Delete.

Restore from any archived/trashed state MUST return to the existing unmasked Pending state, preserving drafts/data and Platform ID. Never restore directly to Active or Disabled.

## Builder scope

1. Create one topic branch for this work only.
2. Expose archived and trashed Service records through the existing Service Home focused-task/bin pattern using the already-declared Service bin table schemas and existing Station APIs.
3. Expose archived and trashed Categories through the same established surface grammar, using existing Category fetch/restore/delete APIs.
4. Reuse existing Admin Station list/table, focused-task, confirm/prompt, split-button, notification, Station lifecycle, identity, and footer primitives.
5. Add/extend mounted regression coverage for:
   - archived/trashed lists;
   - label + Platform ID visibility;
   - Restore re-entry as Pending;
   - correct action availability;
   - guarded permanent delete;
   - no second lifecycle/status/identity path.
6. Update affected Code Maps and lifecycle Known gaps only after implementation reflects the change.
7. Run from `wp-content/plugins/qsd-platform/`:
   - `npm test`
   - `npm run docs:check`
8. Push the topic branch, record exact SHA/evidence here, set `AWAITING REVIEWER REVIEW`, and stop.

## Hard exclusions

- No new lifecycle engine, status model, restore semantics, identity mechanism, drawer system, notification system, footer grammar, or backend authority.
- No Settings/Connections/Rezdy work.
- No Phase 6.2+ work.
- No production deployment.
- Do not alter existing transition legality merely to make a button appear.

## Safeguard — archived Delete

The locked lifecycle contract currently says permanent delete is legal only for a trashed record. The Owner also wants the archived split control to show Move to Bin / Delete.

Builder must first map those labels to existing legal Station actions. If archived `Delete` cannot be implemented without changing current lifecycle legality or adding a new transition, STOP and record the exact source-level mismatch here for Reviewer/Owner decision. Do not silently change lifecycle rules.

## Required handoff evidence

- topic branch + exact pushed SHA;
- changed-file list;
- focused explanation of where Service and Category bin surfaces are mounted;
- proof Restore still returns Pending;
- proof Platform IDs are preserved/displayed;
- action/confirmation behaviour;
- regression results;
- `npm test` and `npm run docs:check` results;
- explicit confirmation no Phase 6.2+, Settings, deployment, or lifecycle redesign was included.


## Owner UI clarification — row and action grammar

Use one compact row per record in the single Bin surface:

`Item name/label | Platform ID | state pill | one split-action control`

State pill:
- Archived
- Trash

There must not be separate Restore/Delete buttons scattered across the row. Use one shared split/dropdown action control.

Action order requested by Owner:

Archived row:
1. Restore
2. Move to Trash
3. Permanently delete

Trash row:
1. Restore
2. Permanently delete

Restore is always the first/default action.

Important lifecycle safeguard:
- Current locked lifecycle/backend only permits permanent delete for a trashed record.
- Therefore Builder must NOT make Archived -> Permanently delete work by changing lifecycle/backend legality.
- If the Owner intends direct permanent delete from Archived to become legal, that is a separate explicit lifecycle decision and must be reviewed before implementation.
- Until such a decision exists, implement only actions already legal under current lifecycle authority.


## Owner decision — permanent delete inside unified Bin

Owner has explicitly changed the prior deletion boundary for the unified Bin model.

Because Archived and Trash now live in ONE Bin surface, permanent delete is allowed from either Bin state.

Legal Bin actions are now:

Archived row:
1. Restore
2. Move to Trash
3. Permanently delete

Trash row:
1. Restore
2. Permanently delete

This supersedes the earlier safeguard that permanent delete was legal only from Trash.

Implementation requirements:
- do not create a second lifecycle or separate Archive/Trash destination;
- extend the existing lifecycle/delete authority only enough to allow permanent delete from `archived` as well as `trashed`;
- keep the same permanent-delete dependency guards, confirmation convention, Platform ID tombstone behaviour, and owning-Station authority;
- Restore semantics remain unchanged: Archived/Trash -> unmasked Pending;
- preserve drafts/data/identity until permanent delete actually succeeds;
- update the locked lifecycle contract, lifecycle Code Map, relevant Station/controller tests, and any delete guard that currently hard-codes Trash-only legality so repository authority matches the Owner decision;
- no other lifecycle transitions are widened.
