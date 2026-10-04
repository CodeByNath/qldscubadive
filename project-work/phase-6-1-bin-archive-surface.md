# Phase 6.1 — Bin / Archive Surface

Status: ACCEPTED
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


## Builder handoff

Executor: Claude Code (local clone, Owner-authorised Git credentials).

Topic branch: `phase-6-1/bin-archive-surface`
Pushed SHA: `9b0800ec8b872f598bb21807835b0ca9352a5902` (verified with `git ls-remote`)
Base `main`: `aa59eff28c768f11d54f9e2d70444ea92af14951`
Remote branches: `main`, `staging`, `Project-work-instructions`, `phase-6-1/bin-archive-surface` (4).

Changed files (28): backend `StationLifecycle.php`, `ServiceController.php`, `AdminCategoriesController.php`, `CategoryMeta.php`; frontend new `service-station/surface/serviceHomeBin.ts` and `presentation/ServiceBinLane.tsx`, edited `ServiceLowerDeck.tsx`, `station-manager/registry/templateKits.ts`, `StationSurfaceHost.tsx`, `admin-station.css`, comments in `api/endpoints/admin.ts` and `useCategoryStation.ts`; tests `service-lifecycle-mask.php`, `category-pending-lifecycle.php`, new `scripts/service-home-bin-regression.mjs`, `station-tabset-contract.ts`, `package.json`; docs: lifecycle contract §5/§8, lifecycle, Service Station, Categories, Service Catalogue, station tab set, list system, navigation and surface binding Code Maps, `service-station/CLAUDE.md`, roadmap.

Where it is mounted: a fourth `Bin` lane in Service Home's lower deck (`ServiceLowerDeck.tsx`, after Details/Connections/Settings). Services and Categories share that one Bin. `serviceHomeBin.ts` reads `fetchAdminCatalog('archived'|'trashed')` and `fetchAdminCategories('archived'|'trashed')` and maps each action 1:1 onto the existing endpoint functions: `restoreService`/`restoreCategory`, `trashService`/`updateCategoryStatus(id,'trashed')`, and `permanentDeleteService`/`permanentDeleteCategory`. The lane reloads when selected.

Row and action behaviour: each row shows `name (Service|Category) | Platform ID | Archived/Trashed pill | one StationSplitAction`. Restore is the primary half. Archived rows show Move to Trash, then Permanently delete; Trash rows show Permanently delete only. Move to Trash and Permanently delete are armed and confirmed in place (`useInlineConfirm`); while armed, the split control is replaced by the prompt plus Confirm/Cancel. Owner guard messages (Category assigned-Services 409) are shown and the row stays.

Lifecycle change (Owner decision): `StationLifecycle::canDelete` now returns `isBinned()` (archived or trashed). Controller messages and comments are updated. No other transition is widened; restore and Platform ID tombstoning are unchanged.

Evidence:
- Restore → Pending: PHP restore from archived and from trashed gives `platform_status=disabled`, an empty `previous_platform_status`, identical `module_status`, and the same `platform_id` (Service; the Category restore test already existed). The mounted regression shows Restore calls only `/restore`, makes no `/status` write, never sends `active`, and the row leaves the Bin.
- Platform IDs: displayed in every row (regression). Archive, restore and archived delete keep or return the same ID, and the tombstone resolves as deleted (PHP, Service and Category).
- Delete legality: archived and trashed are accepted; draft, active and disabled are rejected, and a live Pending delete returns 422 (PHP).
- `npm test`: exit 0, with typecheck, all PHP tests, the 26-route baseline unchanged, the build, and 19/19 JS checks including the new `regression:service-home-bin`.
- `npm run docs:check`: passed (41 Markdown files, 18 Code Maps).

Deviations and limitations for Reviewer:
- Added optional `TemplateKitProps.refetch`, passed by `StationSurfaceHost` (the same handle the drawer already receives), so a Bin action refreshes Details. This is a small additive Station Manager contract change.
- The pill label is the existing `TRAVEL_PILL` "Trashed", not "Trash". I did not change the contract chokepoint without a decision.
- The Bin is a deck lane, not `FocusedTaskShell`; §9 scopes that shell to drawer detours, and the roadmap calls for a Service Home lane.
- `admin-station.css` was already over the size limit (2,440 lines) and is now 2,490 (+50 Bin rules, next to the Service deck rules). `ServiceController.php` gained +1 comment line. Both are flagged under the file-size governance rule rather than split in this phase.
- No browser, WordPress-runtime, CI or staging check was performed. No PR was opened.

Not included: Phase 6.2+, Settings/Connections/Rezdy, deployment, or lifecycle redesign.

Builder stops here for Reviewer review.

## Builder handoff addendum

Three items I raised only in chat, now recorded here:

- **Work-file length:** this file is about 1,330 words, against the roughly 600-word guidance in `project-work/AGENTS.md`. It was already over before the Builder handoff was added. Reviewer may condense or archive earlier sections when recording the verdict.
- **Executor environment (not a source defect):** the Builder clone lives under an iCloud-synced `~/Documents`, and iCloud kept creating "* 2" duplicate folders inside the git-ignored `node_modules`. That broke the first local `tsc` run until `npm ci` reinstalled; the final `npm test` and `docs:check` results above came from a clean reinstall. No tracked file is affected (checked: no "* 2" paths outside `node_modules`). Local results should be read with this in mind; CI on GitHub is unaffected.
- **Project History (Owner decision pending):** per `AGENTS.md`, Phase 6.1 may qualify as a milestone (Bin surface plus the Owner's unified-Bin delete decision). The Builder has asked the Owner and has not created a history document. Record the Owner's answer here once accepted.


## Reviewer decision

Verdict: Proceed with safeguards

Independent review of candidate `9b0800ec8b872f598bb21807835b0ca9352a5902` confirms the core Phase 6.1 implementation is correctly bounded:

- one Bin lane exists on Service Home;
- Service and Category archived/trashed records share that one surface;
- rows show name, Platform ID, travel state, and one split-action control;
- Restore is the primary action;
- Archived actions are Restore / Move to Trash / Permanently delete;
- Trash actions are Restore / Permanently delete;
- permanent delete legality is widened only to the two Bin states;
- restore remains archived|trashed -> unmasked Pending;
- owning Station APIs, dependency guards, confirmation, and Platform ID tombstoning are preserved;
- no Phase 6.2+, Settings/Rezdy, or deployment work was included.

### Required Builder correction

The Owner required the single Bin to include a simple state filter. The pushed implementation has no filter control; it always renders all rows.

Make only this bounded correction on the existing topic branch:

1. Add one compact Bin filter with exactly:
   - All
   - Archived
   - Trash
2. Default to All.
3. Filter the already-loaded unified Bin rows in presentation/state only. Do not create a second surface, storage path, lifecycle path, or duplicate endpoint set.
4. Keep one row grammar:
   `Item name | Platform ID | state pill | split actions`.
5. The Bin-visible state label should read `Archived` or `Trash`. Do not globally change another surface's lifecycle wording if that would create unrelated scope; localise the Bin presentation label if needed.
6. Extend `service-home-bin-regression.mjs` to prove:
   - default All shows both states;
   - Archived shows only archived rows;
   - Trash shows only trashed rows;
   - switching filters does not trigger lifecycle mutations or create a second fetch/storage path.
7. Re-run `npm test` and `npm run docs:check`.
8. Push the corrected exact SHA, update this same work file to `AWAITING REVIEWER REVIEW` with evidence, and stop.

Do not alter the accepted lifecycle/delete work or widen scope while making this correction.


## Builder correction handoff — Bin state filter

Executor: Claude Code. This makes only the bounded correction.

Topic branch: `phase-6-1/bin-archive-surface`
Corrected SHA: `a08fb692615effec4075de734634ff4d2cce7353` (verified with `git ls-remote`; previous candidate `9b0800e…`)
Remote branches: `main`, `staging`, `Project-work-instructions`, `phase-6-1/bin-archive-surface` (4).

Changed in this correction (7 files): `ServiceBinLane.tsx`, `drawer-kit/ui/TravelStatusPill.tsx`, `admin-station.css` (+5 lines: `.cz-service-bin__toolbar`), `scripts/service-home-bin-regression.mjs`, `docs/code-map/service-catalogue.md`, `docs/roadmap.md`, `service-station/CLAUDE.md`. No backend, lifecycle, endpoint, delete or restore file changed (`git diff --name-only 9b0800e a08fb69`).

What changed:
1. **Filter:** one compact `select` (the same shared field-system control the Details catalogue filters with), labelled "Filter Bin by state", with exactly All / Archived / Trash, defaulting to All.
2. **Filtering:** `filterServiceBinRows(rows, filter)` narrows the already-loaded unified rows in component state. It adds no fetch, surface, storage or lifecycle path. Changing the filter also disarms any open in-place confirm. Empty states: "The Bin is empty." (nothing in the Bin), "No archived records.", or "Nothing in Trash.".
3. **Row grammar:** unchanged — `name | Platform ID | state pill | one split action`.
4. **State label:** the Bin pill reads `Archived` / `Trash`. I localised it with a new optional `label` prop on the shared `TravelStatusPill` (it defaults to the existing label). The shared `TRAVEL_PILL` mapping and classes are unchanged, and no other surface's wording changed. Flagged because it is a small additive change to a shared drawer-kit component.

Evidence:
- `regression:service-home-bin` (new section 3b): the filter offers exactly `all:All`, `archived:Archived`, `trashed:Trash`; it defaults to All; All shows both states; Archived shows only `service:801` and `category:31`; Trash shows only `service:802` and `category:32`; filtered rows keep the one row grammar; switching filters made zero requests (no mutation, no Bin re-fetch). The pill checks now require exactly `Archived` / `Trash`.
- `npm test`: exit 0, with typecheck, all PHP tests, the 26-route baseline unchanged, the build, and 19/19 JS checks.
- `npm run docs:check`: passed (41 Markdown files, 18 Code Maps).

Limitations and notes:
- `admin-station.css` is now 2,495 lines (it was already over the limit at 2,440 before Phase 6.1). Still flagged under the file-size rule.
- The iCloud "* 2" duplicates reappeared in `node_modules` and broke `tsc` again. This time I deleted only the git-ignored duplicate folders (41) instead of reinstalling, then ran the full suite. No tracked file affected.
- No browser, WordPress runtime, CI or staging check was performed. No PR was opened.
- Project History remains an Owner decision pending.
- The work file is now longer still (well over the ~600-word guidance); Reviewer may condense it.

Not included: Phase 6.2+, Settings/Rezdy, deployment, or lifecycle changes.

Builder stops here for Reviewer review.


## Reviewer decision — filter correction

Verdict: Proceed with safeguards

Independent review of corrected candidate `a08fb692615effec4075de734634ff4d2cce7353` confirms the bounded UI correction is correct:

- one unified Bin remains;
- filter options are exactly All / Archived / Trash;
- default is All;
- filtering operates only on already-loaded unified rows;
- filter changes issue no fetch or lifecycle mutation;
- row grammar remains name / Platform ID / state pill / one split action;
- the Bin-local pill wording is Archived / Trash without globally changing the shared travel mapping;
- no backend/lifecycle/delete/restore source changed in the correction;
- regression coverage was extended for filter behavior;
- Builder reports `npm test` and `npm run docs:check` passing.

### Required Builder correction

One Code Map sentence is now stale and contradicts the implemented Phase 6.1 state.

In `docs/code-map/service-catalogue.md`, under **Data boundaries**, the sentence:

`Archived rows contribute to the overview count only; archived and trashed Services are not browsable from Home yet (see Lifecycle#known-gaps).`

is no longer true because the accepted candidate adds the Service Home Bin lane.

Make only this documentation correction on the existing topic branch:

1. Update/remove that stale sentence so it accurately states current Bin reachability.
2. Do not change source, lifecycle, UI, filter behavior, or any other Phase 6 item.
3. Run `npm run docs:check`.
4. Push the exact corrected SHA.
5. Update this same work file to `AWAITING REVIEWER REVIEW` with the SHA and docs-check evidence.
6. Stop.

No other correction is requested.


## Builder correction handoff — Code Map consistency

Executor: Claude Code. Documentation-only correction.

Topic branch: `phase-6-1/bin-archive-surface`
Corrected SHA: `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` (verified with `git ls-remote`; previous candidate `a08fb69…`)
Remote branches: `main`, `staging`, `Project-work-instructions`, `phase-6-1/bin-archive-surface` (4).

Changed: only `docs/code-map/service-catalogue.md` (`git diff --name-only a08fb69 8d19492`). No source, lifecycle, UI or filter change.

The stale sentence under **Data boundaries** now reads:
"In `Details`, archived rows contribute to the overview count only; archived and trashed Services (and Categories) are browsed and acted on in the `Bin` lane (see Lifecycle#bin-travel-surface)."
The link now points at the existing `## Bin travel surface` heading in `lifecycle-system.md` instead of `#known-gaps`. I searched `docs/` and `resources/` for other "not browsable from Home" wording and found none. The map is 588 words, within the 600 limit.

Evidence: `npm run docs:check` passed (41 Markdown files, 18 Code Maps). `npm test` was not re-run because the change is documentation-only and the work file requires only `docs:check` for this step.

Builder stops here for Reviewer review.


## Reviewer decision — final Phase 6.1 candidate

Verdict: Proceed

Independent review of final candidate `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` confirms:

- the final correction changes only `docs/code-map/service-catalogue.md`;
- the stale Home-reachability sentence is corrected;
- the Code Map now accurately points archived/trashed Service and Category travel work to the single Service Home `Bin` lane;
- the link targets the existing lifecycle `#bin-travel-surface` section;
- no source, lifecycle, UI, filter, endpoint, identity, deployment, or Phase 6.2+ behavior changed in this correction;
- the accepted Phase 6.1 implementation remains one unified Bin with All / Archived / Trash filtering, row identity + Platform ID + state pill + one split action, Restore-first action order, archived/trashed permanent-delete legality only inside Bin states, and unchanged Pending restore semantics;
- Builder reports `npm run docs:check` passed for the final docs-only correction, while the preceding candidate already passed the full `npm test` suite.

No further implementation correction is required.

## Next Builder action — promote Phase 6.1

1. Promote exact topic head `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` from `phase-6-1/bin-archive-surface` to `main` without changing scope.
2. Verify the resulting `main` SHA contains that exact accepted candidate.
3. Verify post-merge `main` CI completes successfully.
4. Do not deploy to staging or production as part of this closeout.
5. After successful promotion verification, delete only `phase-6-1/bin-archive-surface`; preserve `main`, `staging`, and `Project-work-instructions`.
6. Verify the final remote branch list is exactly those three permanent branches.
7. Update this same work file with:
   - resulting `main` SHA;
   - CI run/result;
   - final branch list;
   - confirmation no deployment occurred;
   - `Status: AWAITING REVIEWER REVIEW`.
8. Stop. Do not begin Phase 6.2 until Reviewer closes Phase 6.1.


## Builder promotion attempt — blocked by executor permission

Status remains `BUILDER ACTION REQUIRED` (no step of the promotion was partially advanced).

- Preflight passed: `origin/main` `aa59eff28c768f11d54f9e2d70444ea92af14951` is an ancestor of accepted head `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d`, so promotion is a pure fast-forward. `ci-deploy.yml` runs `deploy-staging` only for `refs/heads/staging` or `workflow_dispatch`, so a `main` push does not deploy.
- Blocked: Claude Code's auto-mode safety classifier refused `git push origin 8d19492…:refs/heads/main` ("Merge Without Review"). The Owner must allow it on this executor. `main` is unchanged, the topic branch was not deleted, and nothing was deployed.
- Required execution surface: the Owner approves the fast-forward push to `main` in Claude Code (or performs it), after which Builder verifies the `main` SHA and post-merge CI, deletes only `phase-6-1/bin-archive-surface`, verifies three remote branches, and records the evidence here.


## Builder promotion result

The Owner allowed the push on the executor; the earlier block is cleared.

- Promoted accepted head `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` to `main` by fast-forward (`aa59eff..8d19492`). No new commit and no scope change.
- Resulting `main` SHA: `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d` — identical to the accepted candidate (verified with `git ls-remote`).
- Post-merge CI: GitHub Actions run `37207072950` (`Test and deploy`, run 21, push to `main` on `8d19492`) completed with `success`. The `test` job passed `npm ci`, `npm test` and the built Admin Station artifact upload. `deploy-staging` was `skipped`.
- No staging or production deployment occurred.
- Topic branch `phase-6-1/bin-archive-surface` was deleted after `git merge-base --is-ancestor` confirmed it is contained in `main`.
- Final remote branches: `main` (`8d19492…`), `staging` (`69f68ca…`), `Project-work-instructions` — 3.
- Executor note: the first CI polling script had a syntax error. The run result above comes from a direct GitHub API lookup of the run and its jobs.
- Project History remains an Owner decision pending.

Builder stops here for Reviewer closeout. Phase 6.2 not started.


## Reviewer closeout

Verdict: Proceed

Independent closeout verification confirms:

- accepted Phase 6.1 candidate is now on `main` at `8d194929ff9af3d52e290fe4aa8f6b3c593ec66d`;
- GitHub Actions run `37207072950` completed successfully on that exact SHA;
- `npm ci`, `npm test`, and Admin Station artifact upload all passed;
- `deploy-staging` was skipped;
- no staging or production deployment occurred;
- remote branches are exactly `main`, `staging`, and `Project-work-instructions`;
- the completed topic branch `phase-6-1/bin-archive-surface` is removed;
- Phase 6.2 has not started.

Phase 6.1 is accepted and closed.

Do not open Phase 6.2 from this file. The next cycle must create a new active work file for the next roadmap item.
