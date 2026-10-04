# Claude Code startup

Repository-wide AI guidance lives in [`AGENTS.md`](AGENTS.md). Claude Code must read and follow it before beginning work.

## QSD project cycle trigger

When the Owner says `run the cycle`, `continue the work`, `review the latest work`, `check the builder`, or equivalent, **do not interpret the request as "run tests" or "run the repository validation suite."**

Instead, before normal source work:

1. Verify the repository is exactly `CodeByNath/qldscubadive`.
2. Fetch `origin/Project-work-instructions`.
3. Read `project-work/AGENTS.md` from that branch.
4. Read `project-work/PROJECT-RULES.md` when relevant.
5. Read the single active work file and follow its current Status, Phase, actor, scope, exclusions, evidence requirements, and next action literally.
6. Then read the required `main` authority from `AGENTS.md`, `docs/ai-index.md`, `docs/roadmap.md` when current state matters, the relevant Code Map, required architecture contract/skill, and authoritative source.
7. Determine whether Builder or Reviewer owns the next action and perform only that role.
8. Run tests only when the active work file requires validation as part of that cycle step.
9. Update the same active work file with the required evidence/handoff and stop at the defined role boundary.

`Project-work-instructions` coordinates work only. It never replaces QSD product architecture or verified `main` source.
