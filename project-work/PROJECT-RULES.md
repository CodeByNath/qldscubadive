# QSD Project Work Rules

This file defines workflow-layer separation. It does not define QSD product architecture.

## Authority layers

```text
AGENTS.md
+ docs/ai-index.md
+ relevant QSD architecture/contracts
+ relevant Code Map navigation
+ verified main implementation
= repository/product/system authority within their stated scopes

Project-work-instructions
= active work coordination only

Google Drive chat handover
= continuity context only
```

- This branch may coordinate work but must not invent or redefine QSD architecture.
- If an architectural decision is required, record the decision/gate in the active work file and update the appropriate `main` authority through separately authorised work.
- `main` source remains authoritative for implementation state.
- `docs/roadmap.md` remains QSD roadmap/current-state authority; active work files coordinate one controlled phase and must not silently replace the roadmap.
- Do not assume local, pushed, CI, staging-deployed, and live-runtime states are identical.
- Reviewer verdicts are `Proceed`, `Proceed with safeguards`, and `Stop — architectural risk`.

## Operating governance

`docs/foundation/repository-governance.md` on `main` controls file limits, remote-branch capacity, Code Map maintenance, and coordination-branch separation.

Normal permanent branches after bootstrap:

- `main`
- `staging`
- `Project-work-instructions`

At most one additional topic branch is permitted at a time.

For demonstrated subjects, begin with the relevant Code Map and then inspect linked authority and source.

## QSD architecture boundaries

Workflow coordination must preserve current repository authority, including:

- QSD-only repository isolation;
- Station ownership boundaries;
- Platform Identifier authority and identity-preserving composition;
- the locked Station/Drawer lifecycle contract;
- `qsd/v1` as the platform API boundary;
- WordPress as runtime/storage host only;
- staging-only deployment unless Owner explicitly authorises production work.

Never import WEXdesigns or CompuZign product architecture merely because their workflow/governance structure was used as a reference.

## Builder responsibilities

- Obey current phase and scope.
- Implement only authorised work.
- Run required checks.
- Commit logically and push the work.
- Provide exact commit/diff/test evidence.
- Update the same active work file for Reviewer handoff.
- Stop at the phase boundary.

## Reviewer responsibilities

- Inspect actual pushed source and diff.
- Compare the work against QSD repository authority.
- Inspect supplied verification evidence independently.
- Identify architecture, identity, lifecycle, compatibility, security, deployment, runtime, and regression risks as relevant.
- Issue one defined verdict and prepare the precise next action.
- Never implement the Builder's correction while acting as Reviewer.
