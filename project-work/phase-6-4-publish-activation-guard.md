# Phase 6.4 — Publish activation guard

Status: BUILDER ACTION REQUIRED
Phase: Phase 6.4 — Atomic Publish
Actor: Builder

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md` — Phase 6 item 4
4. `docs/code-map/lifecycle-system.md`
5. `docs/code-map/service-station.md`
6. `docs/code-map/categories.md`
7. `docs/architecture/StationDrawerLifecycleContract-v1.md`
8. authoritative Service/Category Station hooks, API adapters, tests and regressions

## Existing source fact

Publish is one user action but both current Station hooks split it into two requests:

- Service: `settleAllServiceModules()` then `updateServiceStatus(... active)`
- Category: `settleCategoryOverview()` then `updateCategoryStatus(... active)`

Both hooks currently continue to the activation request even when the settle response reports failure.

Phase 6.3 now independently rejects invalid activation server-side, but the Publish action must itself stop when settlement fails.

## Required outcome

Implement the roadmap's bounded **stop-on-settle-failure** path for both conforming Stations.

For one Publish click:

1. settle the eligible saved module(s);
2. if settlement does not succeed, stop immediately;
3. do not send the activation request;
4. do not report Publish success;
5. preserve the record in its current state so the user can correct/retry;
6. only after successful settlement may Publish request `active`.

Do **not** add a new publish endpoint in this phase unless repository evidence proves the stop-on-failure path cannot satisfy the contract. If a new endpoint becomes necessary, stop and record a decision gate rather than inventing it.

## Phase 6.3 safeguard

Preserve `active -> active` only as Publish compatibility for an already-active record with pending module changes. It may occur only **after successful settlement**. Do not widen same-state lifecycle transitions.

## User-action boundary

Restore must remain a neutral return to **unmasked Pending**. It must never choose Publish/Active, explicit Disable, or Enable for the user. Do not alter Restore, Disable, Enable, Archive/Trash, or permanent-delete semantics in this phase.

## Builder scope

1. Create the one permitted Phase 6.4 topic branch.
2. Correct Service `publishService()` so failed settlement cannot fall through to activation.
3. Correct Category `publishCategory()` the same way.
4. Keep successful Publish behaviour and mounted state updates intact.
5. Add focused mounted/regression coverage proving for each Station:
   - settle failure sends no activation request;
   - settle failure leaves the record non-activated and reports no Publish success;
   - successful settlement is followed by exactly one activation request;
   - already-active republish with pending changes follows settle-success -> idempotent active only;
   - no Restore/Disable/Enable request is introduced or reused.
6. Update Code Maps/roadmap after source matches.
7. Run from `wp-content/plugins/qsd-platform/`:
   - `npm test`
   - `npm run docs:check`
8. Push exact topic SHA, update this file to `AWAITING REVIEWER REVIEW` with evidence, then stop.

## Hard exclusions

- No new lifecycle/status vocabulary.
- No new Publish endpoint without a Reviewer/Owner decision gate.
- No server transaction/migration redesign.
- No drawer/footer redesign.
- No Phase 6.5 Trash-confirmation work.
- No identity, storage model, public API, Settings/Rezdy, staging, or production deployment work.
- Restore remains Pending re-entry only; no automatic user-selected state.

## Required handoff evidence

Exact branch/SHA, changed files, request-order/failure regressions for Service and Category, confirmation the Phase 6.3 safeguard is preserved, `npm test`, `npm run docs:check`, and explicit confirmation no Phase 6.5+/deployment work was included.
