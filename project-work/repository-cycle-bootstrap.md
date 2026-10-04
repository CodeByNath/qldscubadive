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

## Current execution state

The ChatGPT GitHub connector has now been granted repository write/ref access and successfully created `Project-work-instructions`. This bootstrap phase may proceed on the current surface.
