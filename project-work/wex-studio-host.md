# WEX /studio host integration — queued QSD work

Status: DEFERRED — QUEUED BEHIND ACCOUNT STATION REVIEW
Actor next: Reviewer to release only after existing QSD Account Station work is accepted/deferred and branch housekeeping is complete
Host: CodeByNath/qldscubadive
WEX coordination: CodeByNath/WEXdesigns project-work/qsd-studio-host.md, Owner-approved contract recorded at f8d2d161cd2292329b7773e573a8899334ae1c8c

## Owner-approved objective

Make the existing independently built WEX Admin Dashboard accessible as QSD /studio/ while leaving original /station/ fully functional. QSD owns its host routing, server-side authorization/session checks, private writable allocation for WEX identity-space storage, and safe independent asset delivery. WEX owns all markup, UI components, presentation/design tokens, identity semantics, approved portable bootstrap and storage adapter. No reuse of legacy QSD UI, drawers, presentation templates or CSS. No QSD business records, data plugs, navigation or Logo wiring. Owner commands each future plug individually.

## Release precondition (do not run immediately)

The active QSD work file project-work/account-station-transfer-batch.md currently says AWAITING REVIEWER REVIEW. Resolve that review, safely complete required promotion/housekeeping and verify capacity before assigning this new phase. QSD staging only when separately authorised; production deployment is excluded. This file is a queued scope record, not a competing active phase or permission to start a topic branch.

## Phase 1 — bounded host Builder task once released

1. Read QSD AGENTS.md, docs/ai-index.md, docs/roadmap.md, docs/foundation/repository-governance.md, Admin Station and Platform ID Code Maps, Station/Drawer contract, and actual main source. Compare existing rewrite/module boot, assets, access gates and deployment boundaries. Read WEX contract as integration input, not QSD product authority.
2. Implement a separate /studio/ host route/document with QSD-approved access control, using existing WordPress session and authorization capabilities; unauthorized callers cannot retrieve protected initialization or mutate identity. Do not change /station/ or its QSD bundle.
3. Provide a versioned WEX-only asset mount/delivery seam that serves supplied WEX bundles without importing QSD visual code or depending on GitHub Pages paths.
4. Provide a host-private writable identity storage root with isolation, readback, fail-closed permission checks, and administrator-approved first initialization. Do not create WEX IDs in QSD PlatformIdentifierStation; host business identity and WEX UI identity remain separate. Storage init approval is a runtime action; do not silently preapprove it.
5. Expose only the minimum authorized host handshake required by WEX's approved bootstrap; do not invent business-data API or broad generic adapters. If contract requires new QSD ownership/architecture authority, stop for Reviewer before implementation.
6. Verify anonymous/unauthorized denial, approved access, route conflict-free coexistence with /station/, WEX asset isolation, storage integrity/restart, and regression of existing QSD endpoints. Run npm test and npm run docs:check. Record relevant browser/host tests; staging deployment requires separate authorization.

## Handoff

One governed QSD topic branch only after Reviewer releases the phase. Push candidate and verify exact remote SHA; update this same work file to AWAITING REVIEWER REVIEW with diff, test and host evidence. Stop; Reviewer then releases WEX Builder to complete WEX-side integration. Do not claim live /studio/ is deployed or validated merely from tests.
