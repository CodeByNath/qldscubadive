# Builder role routing — governance maintenance

Status: BUILDER ACTION REQUIRED (governance-only after active feature boundaries permit)
Builder: BUILDER
Scope: CodeByNath/qldscubadive

## Owner-approved adjustment
Retain existing `run the cycle` and independent Reviewer. Add the aliases `BUILDER`, `BUILDER:CODER`, `BUILDER:DESIGNER` in root `AGENTS.md` and delegate-only `CLAUDE.md`; ensure `Project-work-instructions/project-work/AGENTS.md` and `PROJECT-RULES.md` stay aligned (already updated). **Only Owner** may approve manual override. No new approval flows or distinct project cycles.

## Platform skill routing
Existing source: `skills/qsd-platform-architecture/SKILL.md` and `.claude/skills/qsd-platform-architecture/SKILL.md`. Link the architecture skill explicitly from root AGENTS and startup, retain its identity composition authority; do not copy CompuZign content.

Use the CompuZign pattern read-only as a **format reference**: root AGENTS is entry authority; CLAUDE delegates; `skills/<platform>-architecture/SKILL.md` explains architecture. Do not modify CompuZign. Do not supersede root project rules. Workstream-specific active files carry builder specialization and independent handoffs. Parallel execution must obey the existing branch cap and avoid simultaneous edits to the same files.

## Delivery
Read source first. Create a bounded topic candidate when existing branch capacity and other active work allow; do not write directly to main or auto-merge. Run required docs/check tests; push a candidate SHA and report to Reviewer. No feature, UI, identity, backend or deployment changes in this governance phase.
