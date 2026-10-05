# Phase 6.4 — Publish activation guard

Status: ACCEPTED
Phase: Phase 6.4 — Atomic Publish
Actor: Reviewer

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

## Builder handoff

Branch `phase-6-4/publish-activation-guard` at `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f`. It is one commit on `main` `6ac7535`; the remote SHA is verified. Remote heads are the 3 permanent branches plus this topic.

**Changed files (6):**
- `useServiceStation.ts` `publishService()`: `if (!settleResult.success) return null;` now runs before any state update or activation. The success path is unchanged: it applies the settled detail, sends one `platform_status: active`, and returns the result. `settled` is always `true` on a returned result.
- `useCategoryStation.ts` `publishCategory()`: the same guard. The `next` fallback to the unsettled record is removed.
- A settle that errors (non-2xx) already threw from `apiClient` before reaching activation; that path is unchanged and is now covered by a test.
- New `scripts/publish-activation-guard-regression.mjs` (242 lines), registered in `package.json` as `regression:publish-activation-guard`. It mounts the real `useServiceStation` / `useCategoryStation` in a probe against a fetch mock that records every request in order.
- Docs: lifecycle-system (Domain state line, Known-gap wording) and roadmap (item 3 accepted, item 4 built).

**Request-order / failure evidence**, per Station (10 scenarios, 50 checks):
- settle `success:false` → only the settle request; no activation; `null` result; record unchanged.
- settle 422 → the same; the hook rejects and the existing drawer runner reports the error.
- Pending + settle ok → settle, then exactly one `active`; success.
- already-active with pending changes + settle ok → settle, then one idempotent `active`.
- already-active + settle failure → no activation.
- every scenario: no `/restore`, `action: disable` or `action: enable` request.

Against the pre-fix hooks the same script fails: settle then `active` is sent, and success is reported after a failed settle. The guard is therefore proven to detect the defect.

**Phase 6.3 safeguard:** `active → active` is sent only after a successful settle. No server code changed, and no same-state transition was widened.

**Observation for the Reviewer:** both drawer controllers already route an *already-active* record's Publish to `settleModules` only (`useServiceDrawerController.ts:118`, `useCategoryDrawerController.ts:133`). So in the drawer, idempotent `active → active` currently arises only through a direct hook call. No change was made.

**Known gap (left honest in the lifecycle map):** Publish is still two requests. An activation failure after a successful settle leaves the record settled but not active. This was not addressed because a single server route is behind a decision gate.

**Checks** (from `wp-content/plugins/qsd-platform/`): `npm test` exit 0 (typecheck, PHP, build, 21/21 JS); `npm run docs:check` passed.

Not included: new publish endpoint, server/transaction changes, Restore/Disable/Enable/Archive/Trash/Delete changes, drawer/footer redesign, Phase 6.5 Trash confirmation, deployment.


## Reviewer decision

Verdict: Proceed

Independent review of candidate `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f` confirms the bounded Phase 6.4 stop-on-settle-failure path is correct:

- the topic branch is exactly one commit ahead of accepted `main` `6ac7535a65e3b2e1bcbff36ad32fc92e31346b65`;
- Service `publishService()` returns immediately when settle reports failure, before local success-state application or any activation request;
- Category `publishCategory()` does the same and no longer falls back to the pre-settle record before activating;
- successful settlement remains followed by exactly one `active` request;
- already-active republish preserves the Phase 6.3 compatibility rule only after successful settlement;
- the mounted regression bundles and exercises the real Service and Category hooks and covers reported settle failure, HTTP settle failure, pending success, active republish success, and active republish failure;
- Publish introduces no Restore, Disable, or Enable request;
- no backend lifecycle, route, identity, storage, drawer/footer, Phase 6.5, or deployment scope was changed.

The remaining two-request limitation is correctly documented as a known gap: if activation itself fails after successful settlement, the record may be settled but non-active. That is not a Phase 6.4 defect because this phase explicitly authorised the stop-on-settle-failure option and prohibited inventing a new server Publish route without a new decision gate.

Builder-reported validation is `npm test` exit 0 and `npm run docs:check` pass. No GitHub Actions run exists for the topic SHA, as expected before promotion.

No source correction is required.

## Next Builder action — promote Phase 6.4

1. Promote exact accepted topic head `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f` from `phase-6-4/publish-activation-guard` to `main` without changing scope.
2. Verify resulting `main` contains that exact accepted candidate.
3. Verify post-push `main` CI completes successfully.
4. Do not deploy to `staging` or production.
5. After successful main/CI verification, delete only `phase-6-4/publish-activation-guard`; preserve the three permanent branches.
6. Verify final remote branches are exactly `main`, `staging`, and `Project-work-instructions`.
7. Update this same work file with main SHA, CI run/result, final branch list, confirmation no deployment occurred, set `Status: AWAITING REVIEWER REVIEW`, set `Actor: Reviewer`, and stop.
8. Do not begin Phase 6.5 until Reviewer closes Phase 6.4.

## Builder promotion result

- Promoted exact accepted candidate `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f` to `main` by fast-forward (`6ac7535..b9192c6`); ancestry checked before push; no scope change.
- `main` = `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f`; `git merge-base --is-ancestor b9192c6… origin/main` confirmed.
- Post-push CI: GitHub Actions run `37250672804` ("Test and deploy") — `completed` / `success`. Jobs: `test` success; `deploy-staging` skipped.
- No deployment: `staging` unchanged at `69f68cafab6b3fb647523782ea560f900e9b86ac`; nothing deployed to staging or production.
- Deleted only `phase-6-4/publish-activation-guard` after the containment check.
- Final remote branches (exactly three): `main` `b9192c6`, `staging` `69f68ca`, `Project-work-instructions`.
- Phase 6.5 not started; awaiting Reviewer close of Phase 6.4.


## Reviewer closeout

Verdict: Proceed

Independent promotion verification completed:

- `main` is exactly `b9192c6abd9deb04c0d1b24a87f70dd4ec99434f`, the accepted Phase 6.4 candidate;
- GitHub Actions run `37250672804` completed successfully on that exact SHA;
- the `test` job passed, including `npm ci`, `npm test`, and Admin Station artifact upload;
- `deploy-staging` was skipped;
- no staging or production deployment occurred;
- topic branch `phase-6-4/publish-activation-guard` is removed;
- remote branches are exactly `main`, `staging`, and `Project-work-instructions`;
- the remaining two-request Publish limitation stays documented as a known gap for later architectural review.

Phase 6.4 is accepted and closed. Phase 6.5 has not started.
