# Repository Cycle Bootstrap

Status: BUILDER ACTION REQUIRED
Phase: Bootstrap QSD controlled Builder/Reviewer cycle

## Owner decision

Adapt the proven WEXdesigns work-coordination structure to `CodeByNath/qldscubadive` without importing WEX product architecture.

QSD must gain:

- permanent `Project-work-instructions` coordination branch;
- `project-work/AGENTS.md` and `project-work/PROJECT-RULES.md` on that branch;
- root `AGENTS.md` cycle routing on `main`;
- durable repository-governance documentation on `main`;
- one rolling Google Drive chat handover for continuity only;
- existing QSD Code Maps, architecture, source-first rule, `docs/roadmap.md`, staging deployment boundary, and validation commands preserved.

## QSD-specific branch rule

Unlike WEXdesigns, QSD permanently needs `staging`.

Normal permanent branches:

- `main`
- `staging`
- `Project-work-instructions`

Allow at most one additional active topic branch. Maximum normal remote branch count: four.

## Builder task

1. Create/sync `Project-work-instructions` from current `main`.
2. Add the QSD-specific coordination files from the reviewed bootstrap package.
3. Create one topic branch for the main-side governance bootstrap.
4. Add `docs/foundation/README.md` and `docs/foundation/repository-governance.md`.
5. Update root `AGENTS.md` so cycle triggers route first to `origin/Project-work-instructions`, while preserving all current QSD architecture, lifecycle, validation, and deployment rules.
6. Update `docs/ai-index.md` only enough to route controlled-work governance and Foundation correctly; do not duplicate project-work rules.
7. Do not change product/source implementation.
8. Run `npm run docs:check` from `wp-content/plugins/qsd-platform/`.
9. Push the topic branch, verify remote SHA, update this file to `AWAITING REVIEWER REVIEW` with evidence, push the coordination branch, and stop.

## Required evidence

- remote branch list;
- topic branch and exact SHA;
- changed-file list;
- `npm run docs:check` result;
- confirmation that no source/runtime/deployment files changed;
- confirmation Google Drive handover exists and remains context-only.

## Builder handoff

Candidate branch: `docs/qsd-cycle-bootstrap`  
Candidate SHA: `96d4929883c17965ed22c40c7385acec6b2fdcbf`  
Base `main`: `512c77189e14bbda9ac5bb12476f954853f5a4fe`

Changed files:

- `AGENTS.md`
- `docs/ai-index.md`
- `docs/foundation/README.md`
- `docs/foundation/repository-governance.md`

Evidence:

- Remote branches verified: `main`, `staging`, `Project-work-instructions`, `docs/qsd-cycle-bootstrap`.
- Compare against `main`: 4 commits, 4 files, 99 additions, 3 deletions; no source/runtime/deployment files changed.
- Draft PR #1 opened only to obtain CI evidence.
- GitHub Actions run `37179139576` (`Test and deploy`, run 17) completed successfully on candidate SHA.
- The workflow runs `npm test`; current QSD `npm test` includes `docs:check`, satisfying the required validation boundary.
- Google Drive handover exists at `QLD-ScubaDive / Chat Handover / QLD-ScubaDive Chat Handover` and remains context-only.

Builder stops here for independent Reviewer review. Do not merge or begin the Settings/Connections implementation from this status.


## Reviewer decision

Verdict: Proceed

Reviewer independently verified:

- candidate branch `docs/qsd-cycle-bootstrap` is based on current `main` `512c77189e14bbda9ac5bb12476f954853f5a4fe`;
- candidate head is `96d4929883c17965ed22c40c7385acec6b2fdcbf`;
- diff is limited to four governance/documentation files;
- no product source, runtime, deployment workflow, Station, identity, lifecycle, or API implementation changed;
- root `AGENTS.md` correctly routes controlled cycle triggers to `Project-work-instructions` while preserving QSD authority;
- Foundation governance is operating policy only and does not import WEX product architecture;
- QSD's permanent `staging` branch is preserved and the four-branch ceiling correctly allows one topic branch;
- Google Drive remains continuity context only;
- GitHub Actions run `37179139576` passed on the exact candidate SHA.

No architectural correction is required.

## Next Builder action — promote bootstrap

1. Merge draft PR #1 / candidate `96d4929883c17965ed22c40c7385acec6b2fdcbf` to `main` without altering scope.
2. Verify the resulting `main` SHA and that the four reviewed files match the accepted candidate.
3. Verify the post-merge `main` CI result.
4. After successful promotion verification, delete only `docs/qsd-cycle-bootstrap`; preserve `main`, `staging`, and `Project-work-instructions`.
5. Update this same work file with promotion SHA, CI evidence, and final remote branch list, then set `AWAITING REVIEWER REVIEW`.
6. Stop. Do not begin Settings/Connections/Rezdy architecture work until bootstrap closeout is accepted.
