# Phase 6.3 — Server-side transition enforcement

Status: BUILDER ACTION REQUIRED
Phase: Phase 6.3 — Server-side transition enforcement
Actor: Builder

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md` — Phase 6 item 3
4. `docs/code-map/lifecycle-system.md`
5. `docs/code-map/service-station.md`
6. `docs/code-map/categories.md`
7. `docs/architecture/StationDrawerLifecycleContract-v1.md`
8. `skills/qsd-platform-architecture/SKILL.md`
9. authoritative source/tests reached from those maps

## Existing source fact

Both status handlers still accept a valid target and pass it through `StationLifecycle::applyStatus()`:

- `ServiceController::updateStatus()`
- `AdminCategoriesController::updateStatus()`

The lifecycle engine already owns strict transition computations: `publish()`, `archive()`, `trash()`, `restore()`, plus explicit Disable/Enable masking in each Station. The UI currently prevents most illegal calls, but the server does not.

## Required outcome

Make the owning backend Station enforce the locked lifecycle regardless of caller.

For status-route target requests:

- `active` = Publish transition only. It must be legal from the lifecycle engine's publish states **and** the entity Overview must be complete and `settled`.
- `archived` = `StationLifecycle::archive()`.
- `trashed` = `StationLifecycle::trash()`.
- direct `platform_status: disabled` must not bypass the explicit Disable/Enable mask contract; reject it and require the existing `action: disable|enable` request shape.
- Service's deprecated `is_active` compatibility input must not bypass these guards. Preserve the accepted input contract, but resolve it through the same strict server rules rather than permissive application.

Return a 4xx response for illegal transitions without mutating status, previous status, module state, drafts, canonical data, or identity.

## Publish readiness

Service activation is permitted only when:
- the canonical Overview is complete by the existing Service-owned completeness rule; and
- `module_status.overview === settled`.

Category activation is permitted only when:
- the canonical Category Overview is complete by the existing Category-owned completeness rule; and
- `module_status.overview === settled`.

Do not require optional child modules (Inclusions/FAQs) to be configured or settled merely to activate a Service.

## Builder scope

1. Create the one permitted Phase 6.3 topic branch.
2. Replace permissive status application in the two owning controllers with the existing strict lifecycle operations and entity-owned readiness checks.
3. Keep explicit Disable/Enable and existing Restore/Delete paths intact.
4. Preserve route paths, methods, request schema/backward-compatible inputs, Platform IDs, response identity, and Station ownership.
5. Add focused backend tests proving legal and illegal transitions for both Service and Category, including:
   - active rejected when Overview incomplete;
   - active rejected when Overview is pending/unsettled;
   - active accepted only when complete + settled and lifecycle-legal;
   - archive/trash reject illegal source states;
   - direct disabled target is rejected;
   - rejected calls do not mutate lifecycle/module/identity state;
   - Service legacy `is_active` cannot bypass the same rules.
6. Update lifecycle/Station Code Maps and roadmap only after implementation matches.
7. Run from `wp-content/plugins/qsd-platform/`:
   - `npm test`
   - `npm run docs:check`
8. Push exact topic SHA, record evidence here as `AWAITING REVIEWER REVIEW`, and stop.

## Hard exclusions

- No Phase 6.4 Atomic Publish changes.
- Do not combine settle + activate into one route in this phase.
- No drawer/UI redesign or new confirmation work.
- No new lifecycle engine or status vocabulary.
- No identity, persistence model, endpoint family, public API, Settings/Rezdy, or deployment work.
- Do not alter permanent-delete legality or Restore semantics.

## Required handoff evidence

Exact branch/SHA, changed files, transition matrix tested, proof rejected calls are mutation-free, unchanged route baseline unless intentionally required, `npm test`, `npm run docs:check`, and explicit confirmation Phase 6.4+ and deployment were not included.
