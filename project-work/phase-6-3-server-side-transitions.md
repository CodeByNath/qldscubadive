# Phase 6.3 — Server-side transition enforcement

Status: AWAITING REVIEWER REVIEW
Phase: Phase 6.3 — Server-side transition enforcement
Actor: Reviewer

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

## Builder handoff

Branch `phase-6-3/server-side-transitions` at `6ac7535a65e3b2e1bcbff36ad32fc92e31346b65` (one commit on `main` `087a769`; remote SHA verified). Remote heads: 3 permanent + this topic.

**Changed files (12):**
- `StationLifecycle.php`: the permissive `applyStatus` is replaced by `statusRouteTransition(current, target, previous)`. `active` resolves via `publish()`, `archived` via `archive()`, `trashed` via `trash()`; every other target returns null. No new vocabulary.
- `ServiceController::updateStatus` / `AdminCategoriesController::updateStatus` check, in order:
  1. a direct `disabled` gets a 422;
  2. an engine-illegal transition gets a 422;
  3. `active` without readiness gets a 422;
  4. otherwise the existing write.
  Every refusal returns before any write. `action: disable|enable`, Restore and Delete are untouched. Service's current status now comes from `ServiceSchema::resolvePlatformStatus` (as Restore does), instead of `platform_status ?? 'disabled'`.
- Readiness: `ServiceModules::isPublishReady` (existing `isOverviewComplete` + `module_status.overview === settled`) and `CategoryMeta::isPublishReady` (`deriveOverviewStatus` settled + `module_status.overview === settled`). Inclusions/FAQs never gate.
- Legacy `is_active`: still accepted by the schema. `true` follows the Publish rules; `false` maps to `disabled` and gets the same 422.
- Tests: `tests/service-lifecycle-mask.php`, `tests/category-pending-lifecycle.php`.
- Docs: lifecycle-system (Backend authority; resolved Known-gap bullet removed), service-station, categories, roadmap (item 2 accepted, item 3 built), and the contract §8 Known-gap note (now a conformance bullet).

**Transition matrix tested:**
- Engine: 16 cases.
- Service and Category through the real controllers, each refused case with a before/after snapshot:
  - pending Overview → active refused;
  - incomplete Overview → active refused;
  - complete + settled → accepted;
  - active with an unsettled draft → refused, and → stays active once settled;
  - direct `disabled` refused;
  - archived → archived/active refused;
  - trashed → trashed/archived refused.
- Service only: `is_active` true/false is checked in the same cases.
- The snapshot covers status, mask, `module_status`, drafts, canonical post/term data, terms and Platform ID meta, and is byte-identical after every refusal.
- The existing Service test fixtures for ids 3/4 published without settling, so they now `settleAll` first.

**Route baseline:** `tests/service-route-baseline.php` reports 26 routes unchanged. Paths, methods and args are unchanged.

**Checks** (from `wp-content/plugins/qsd-platform/`):
- `npm test` exit 0 (typecheck, PHP, build, 20/20 JS).
- `npm run docs:check` passed (41 files, 18 Code Maps).

**Decisions for the Reviewer:**
1. `active → active` is accepted as a no-op status write when ready. Both drawers' Publish (settle, then `platform_status: active`) is offered on live records with pending drafts; refusing it would break Publish. Rejecting it instead needs a UI change, and UI changes are excluded here.
2. Publish from a masked Disabled record follows the engine (`disabled → active` is legal). The Category footer still offers Publish while masked, and the mask is left as stored (pre-existing behaviour). Refusing it would add a rule that neither the engine nor the work file states.
3. Archive from a never-published Pending record stays engine-legal; the UI already disables it.

**Governance:** `ServiceController.php` was already over the 1,000-line cap (1,280 → 1,292). The readiness logic was put in the Support classes to keep the growth small. `AdminCategoriesController.php` grew 874 → 883.

Not included: Phase 6.4 Atomic Publish, settle+activate merging, UI/drawer work, deployment, history document.


## Reviewer decision

Verdict: Proceed with safeguards

Independent review of candidate `6ac7535a65e3b2e1bcbff36ad32fc92e31346b65` confirms the Phase 6.3 server enforcement is correctly bounded:

- the topic branch is one commit ahead of accepted `main` `087a76965d89ec9994ff82e8d7fba3a3f24c24f7`;
- permissive `applyStatus()` is removed from the status-route path;
- `archived` and `trashed` resolve through the existing strict engine operations;
- direct `disabled` is rejected in favour of the existing `action: disable|enable` mask path;
- Service legacy `is_active` cannot bypass the same guards;
- Service and Category activation both require their canonical Overview to be complete and the Overview module to be `settled`;
- optional Service child modules do not gate activation;
- refusals occur before persistence and the controller tests snapshot lifecycle/module/draft/canonical/identity state to prove no mutation;
- route paths, methods and arguments remain unchanged;
- Restore, permanent delete, identity and Phase 6.4 Atomic Publish are untouched.

### Safeguard — active Publish compatibility

`statusRouteTransition(active, active)` is accepted only as an idempotent status write supporting the repository's existing Publish workflow for already-active records with pending module changes. Current `deriveCanPublish()` explicitly enables that workflow. Rejecting it in Phase 6.3 would break established source behaviour and require an excluded UI/Atomic-Publish change.

This allowance must not be treated as a new travel transition or widened to other same-state targets. Phase 6.4 must re-evaluate it when Publish becomes atomic.

Builder reports `npm test` and `npm run docs:check` passing locally. No GitHub Actions run exists for the topic SHA, which is expected before promotion.

No source correction is required.

## Next Builder action — promote Phase 6.3

1. Promote exact accepted topic head `6ac7535a65e3b2e1bcbff36ad32fc92e31346b65` from `phase-6-3/server-side-transitions` to `main` without changing scope.
2. Verify resulting `main` contains that exact candidate.
3. Verify post-push `main` CI completes successfully.
4. Do not deploy to `staging` or production.
5. After successful main/CI verification, delete only `phase-6-3/server-side-transitions`; preserve the three permanent branches.
6. Verify final remote branches are exactly `main`, `staging`, and `Project-work-instructions`.
7. Update this same work file with main SHA, CI run/result, final branch list, no-deployment confirmation, set `Status: AWAITING REVIEWER REVIEW`, `Actor: Reviewer`, and stop.
8. Do not begin Phase 6.4 until Reviewer closes Phase 6.3.

## Builder promotion result

- `main` fast-forwarded `087a769` → `6ac7535a65e3b2e1bcbff36ad32fc92e31346b65`. Ancestry was checked before the push; the remote `main` SHA is verified as the exact accepted candidate. Scope is unchanged.
- Post-push `main` CI run `37241389893` (push event) finished `success`. `test` passed, including `npm ci`, `npm test` and the upload. `deploy-staging` was `skipped`.
- No deployment to staging or production.
- After the containment check, `phase-6-3/server-side-transitions` was deleted (remote and local).
- Final remote heads are exactly `main` `6ac7535`, `staging` `69f68ca` and `Project-work-instructions`.
- The `active → active` safeguard is noted for Phase 6.4 re-evaluation, as directed. Phase 6.4 has not been started.
