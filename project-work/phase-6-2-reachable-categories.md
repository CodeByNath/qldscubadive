# Phase 6.2 — Reachable Categories

Status: ACCEPTED
Phase: Phase 6.2 — Reachable Categories
Actor: Reviewer

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


## Builder handoff

Executor: Claude Code (local clone, Owner-authorised Git credentials).

Topic branch: `phase-6-2/reachable-categories`
Pushed SHA: `087a76965d89ec9994ff82e8d7fba3a3f24c24f7` (verified with `git ls-remote`)
Base `main`: `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d`
Remote branches: `main`, `staging`, `Project-work-instructions`, `phase-6-2/reachable-categories` (4).

Changed files (13):
- Source: `service-station/surface/serviceHomeConnections.ts` (the projection no longer drops `assigned_count === 0`), `service-station/presentation/ServiceConnectionsLane.tsx` (adds the filter), `admin-station/styles/admin-station.css` (+12 lines: `.cz-service-connections`, `__toolbar`).
- Tests: `scripts/service-home-connections-contract.ts` (updated: every live Category is projected), new `scripts/service-home-connections-regression.mjs`, `package.json` (registers the new regression).
- Docs: `categories.md`, `service-connections.md`, `service-catalogue.md`, `station-tab-set.md`, `lifecycle-system.md` (removed the now-resolved Known gap), `service-station/CLAUDE.md`, `roadmap.md` (item 1 marked accepted, item 2 built).

Implementation:
- `projectServiceHomeConnectionRows()` maps every live Category from the existing `fetchAdminCategories()` call; no new endpoint.
- `ServiceConnectionsLane` adds one compact field-system `select` ("Filter Categories by connection") with All / Connected / Unassigned, default All. `filterServiceConnectionRows()` narrows the loaded rows in component state: Connected is `connectedCount > 0`, Unassigned is `connectedCount === 0`. Filter-specific empty states are included.
- The row grammar is unchanged (identity, Platform ID, Services count, status pill, one View split). View still dispatches `view-category` with the native id, which `admin-station/register.ts` binds to the existing `category` drawer (not changed).

Regression evidence (`regression:service-home-connections`, mounting the real lane):
- the filter offers exactly `all:All`, `connected:Connected`, `unassigned:Unassigned` and defaults to All; the lane read `GET admin/categories` exactly once;
- All shows all four live Categories (two connected, two unassigned), each with its Platform ID, count, pill and one View;
- Connected shows only `assigned_count > 0`; Unassigned shows only `assigned_count === 0`;
- View on an unassigned Category dispatched `{ recordId: 43 (number), intentId: 'view-category' }`, and the binding to the `category` drawer is asserted from `register.ts`;
- switching filters and clicking View made zero requests; no request other than the single list read was ever made.

Validation:
- `npm test`: exit 0, with typecheck, all PHP tests, the 26-route baseline unchanged, the build, and 20/20 JS checks (including the updated contract and new regression).
- `npm run docs:check`: passed (41 Markdown files, 18 Code Maps). Changed maps stay within 600 words (largest: `service-catalogue.md` at 597).

Scope confirmation: no Category persistence, lifecycle, identity, endpoint, relationship, Platform ID family, Service-assignment, Phase 6.3+, Settings/Rezdy or deployment change. No PHP or backend file touched.

Notes for Reviewer:
- `admin-station.css` grows from 2,495 to 2,507 lines (already over the file-size rule before Phase 6.1); flagged, not split in this phase.
- Fixed a missing comma I introduced in Phase 6.1 in `station-tab-set.md` ("lane `Settings`" → "lane (…), `Settings`") while editing that same sentence.
- iCloud executor note: no `node_modules` "* 2" duplicates were present before this `npm test` run.
- No browser, WordPress runtime, CI or staging check was performed. No PR was opened.
- Project History for Phase 6.1 is still an Owner decision pending.

Builder stops here for Reviewer review.


## Reviewer decision

Verdict: Proceed

Independent review of pushed candidate `087a76965d89ec9994ff82e8d7fba3a3f24c24f7` confirms Phase 6.2 is correctly bounded:

- the topic branch is exactly one commit ahead of accepted `main` `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d`;
- the existing `GET admin/categories` read remains the single Category source;
- `projectServiceHomeConnectionRows()` now preserves every live Category, including `assigned_count === 0`;
- the Connections lane adds exactly All / Connected / Unassigned, default All;
- filtering is over already-loaded rows only;
- Connected is `connectedCount > 0`; Unassigned is `connectedCount === 0`;
- row identity, Platform ID, status pill, Services count and View action remain intact;
- View still dispatches `view-category` with the native numeric Category id and remains bound to the existing Category drawer;
- the mounted regression directly checks one list read, both filter partitions, native-id View dispatch, and no request on filter/View interaction;
- no PHP/backend, lifecycle, persistence, Platform ID family, relationship authority, assignment editing, deployment, or Phase 6.3+ work was introduced;
- Code Maps and roadmap were updated consistently with the candidate.

Builder-reported local validation is `npm test` exit 0 and `npm run docs:check` pass. There are no GitHub Actions runs for the topic SHA, which is expected here because no PR/main push occurred. Browser, WordPress runtime, staging and deployment were not verified and are not required to accept this presentation-only candidate before promotion.

No implementation correction is required.

## Next Builder action — promote Phase 6.2

1. Promote exact accepted topic head `087a76965d89ec9994ff82e8d7fba3a3f24c24f7` from `phase-6-2/reachable-categories` to `main` without changing scope.
2. Verify resulting `main` contains that exact accepted candidate.
3. Verify post-push `main` CI completes successfully.
4. Do not deploy to `staging` or production.
5. After successful main/CI verification, delete only `phase-6-2/reachable-categories`; preserve `main`, `staging`, and `Project-work-instructions`.
6. Verify the final remote branch list is exactly those three permanent branches.
7. Update this same work file with resulting `main` SHA, CI run/result, final branch list, confirmation no deployment occurred, set `Status: AWAITING REVIEWER REVIEW`, set `Actor: Reviewer`, and stop.
8. Do not begin Phase 6.3 until Reviewer closes Phase 6.2.


## Builder promotion attempt — blocked by executor permission

Status remains `BUILDER ACTION REQUIRED`; no promotion step was partially advanced.

- Preflight passed: `origin/main` `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` is an ancestor of accepted head `087a76965d89ec9994ff82e8d7fba3a3f24c24f7`, so promotion is a pure fast-forward. A `main` push does not run `deploy-staging`.
- Blocked: Claude Code's auto-mode classifier refused `git push origin 087a769…:refs/heads/main` ("Merge Without Review"). `main` is unchanged, the topic branch was kept, and nothing was deployed.
- Required execution surface: the Owner allows the fast-forward `main` push on this executor (as for Phase 6.1), then Builder completes the CI verification, topic-branch deletion and evidence steps.


## Builder promotion result

The Owner allowed the push on the executor; the earlier block is cleared.

- Promoted accepted head `087a76965d89ec9994ff82e8d7fba3a3f24c24f7` to `main` by fast-forward (`8d19492..087a769`). No new commit and no scope change.
- Resulting `main` SHA: `087a76965d89ec9994ff82e8d7fba3a3f24c24f7` — identical to the accepted candidate (verified with `git ls-remote`).
- Post-push CI: GitHub Actions run `37240166651` (`Test and deploy`, run 22, push to `main` on `087a769`) completed with `success`. The `test` job passed `npm ci`, `npm test` and the built Admin Station upload. `deploy-staging` was `skipped`.
- No staging or production deployment occurred.
- Topic branch `phase-6-2/reachable-categories` was deleted after `git merge-base --is-ancestor` confirmed it is contained in `main`.
- Final remote branches: `main` (`087a769…`), `staging` (`69f68ca…`), `Project-work-instructions` — 3.
- Project History for Phase 6.1/6.2 is still an Owner decision pending.

Builder stops here for Reviewer closeout. Phase 6.3 not started.


## Reviewer closeout

Verdict: Proceed

Independent promotion verification completed:

- `main` is exactly `087a76965d89ec9994ff82e8d7fba3a3f24c24f7`, the accepted Phase 6.2 candidate;
- GitHub Actions run `37240166651` completed successfully on that exact SHA;
- the `test` job passed, including `npm ci`, `npm test`, and Admin Station artifact upload;
- `deploy-staging` was skipped;
- no staging or production deployment occurred;
- the topic branch `phase-6-2/reachable-categories` is removed;
- remote branches are exactly `main`, `staging`, and `Project-work-instructions`.

Phase 6.2 is accepted and closed. Phase 6.3 has not started.
