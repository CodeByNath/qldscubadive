# Phase 6.2 — Reachable Categories

Status: BUILDER ACTION REQUIRED
Phase: Phase 6.2 — Reachable Categories
Actor: Builder

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md` — Phase 6 item 2
4. `docs/code-map/categories.md`
5. `docs/code-map/service-connections.md`
6. `docs/code-map/service-station.md`
7. `docs/architecture/StationDrawerLifecycleContract-v1.md`
8. `skills/qsd-platform-architecture/SKILL.md`
9. authoritative source reached from those maps

## Existing source fact

`useServiceHomeConnections()` already calls `fetchAdminCategories()` with no status filter, so it receives the live Category list including Categories with `assigned_count === 0`. `projectServiceHomeConnectionRows()` currently removes those rows with `assigned_count > 0`.

The existing Category drawer remains the edit/lifecycle authority. This phase must expose reachability only; it must not create a second Category model or persistence path.

## Required outcome

Make every live Category reachable from Service Home while preserving the existing Connections lane and Category drawer.

Use one presentation/state filter in the existing Connections lane:

- All
- Connected
- Unassigned

Default: All.

Definitions:
- Connected: `assigned_count > 0`
- Unassigned: `assigned_count === 0`

Each visible Category continues to use the established row grammar, Platform ID, status pill and View action. View must open the existing Category drawer by the real native Category ID.

## Builder scope

1. Create the one permitted topic branch for Phase 6.2.
2. Stop filtering unassigned Categories out of the data projection.
3. Add the three-state local filter above to the existing Connections presentation.
4. Filter already-loaded rows only. Changing the filter must not refetch, mutate relationships, or create a new endpoint/storage path.
5. Preserve Category ownership, Platform ID, lifecycle, drawer and Service-owned assignment authority.
6. Add/extend focused regression coverage proving:
   - All includes connected and unassigned live Categories;
   - Connected includes only `assigned_count > 0`;
   - Unassigned includes only `assigned_count === 0`;
   - View still opens the existing Category drawer by native ID;
   - filter changes perform no mutation and no duplicate fetch path.
7. Update affected Code Maps and the roadmap only after source matches the new state.
8. Run from `wp-content/plugins/qsd-platform/`:
   - `npm test`
   - `npm run docs:check`
9. Push the topic branch, verify exact remote SHA, update this same file to `AWAITING REVIEWER REVIEW` with evidence, then stop.

## Hard exclusions

- No Category persistence, lifecycle, identity, endpoint or relationship redesign.
- No new Platform ID family.
- No second Categories Station/list system.
- No Service assignment editing in this phase.
- No Phase 6.3+ work.
- No Settings/Connections/Rezdy architecture work.
- No production or staging deployment.

## Required handoff evidence

Provide exact topic branch/SHA, changed files, regression evidence, `npm test`, `npm run docs:check`, and explicit confirmation that no persistence/lifecycle/identity/API/deployment scope was widened.
