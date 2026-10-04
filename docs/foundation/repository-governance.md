# Repository Governance

## Scope

This Foundation rule governs QSD repository operability: authored-file size, remote branch capacity, Code Map maintenance, and coordination-branch separation. It does not define QSD product behaviour or technical architecture.

## Authored-file size

Authored Markdown and code/config files should normally remain at or below 600 lines. Only the Owner may explicitly approve a file above that working limit. Any approval must record the file, reason, and review/removal point in the active work file.

No new or substantively expanded authored file may exceed 1,000 lines. Existing larger cohesive files are migration/audit concerns, not permission to expand them.

Line count is a navigation signal, not an excuse to fragment cohesive authority. Follow the cohesion and placement rules in `AGENTS.md`.

## Remote branch capacity

QSD has three permanent remote branches:

- `main` — integration;
- `staging` — staging deployment;
- `Project-work-instructions` — Builder/Reviewer work coordination only.

At most one additional topic/work branch may exist at a time. Therefore normal maximum remote branch count is four.

Before creating a topic branch, inspect remote heads. After accepted work is promoted and independently verified, delete the completed topic branch before opening another. Never delete `main`, `staging`, or `Project-work-instructions` as normal housekeeping.

`staging` is a deployment boundary, not a general-purpose work branch.

## Coordination branch

`Project-work-instructions` contains workflow coordination only:

- `project-work/AGENTS.md`;
- `project-work/PROJECT-RULES.md`;
- one active work file plus accepted/deferred historical coordination files as needed.

It must not redefine QSD architecture, Platform IDs, Station ownership, lifecycle, deployment boundaries, or domain rules. Those remain governed by `main` repository authority.

## Code Maps

For a demonstrated subsystem, its Code Map is the first operating stop before opening large implementation files. Maps route to current authority, source, tests, dependency boundaries, and safe change paths; they do not become independent architecture authority.

Update the affected map in the same authorised work whenever ownership, authoritative paths, dependency boundaries, or safe routing change. Keep each subsystem map within the limits already defined by `AGENTS.md`.

## Cross-chat handover

A rolling Google Drive handover is maintained for conversational continuity only. It is context, not authority.

Repository authority always wins. The Drive handover points a clean chat back to QSD repository authority and the active `Project-work-instructions` work file rather than replacing them.
