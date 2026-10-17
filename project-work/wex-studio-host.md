# QSD Studio — automatic WEX staging release consumer

Status: BUILDER ACTION REQUIRED
Phase: 1I — one-time staging auto-consumer integration
Actor: BUILDER:CODER (Claude) — QSD only
Owner authorisation: eliminate manual QSD release pin edits/deployments for each approved WEX UI refinement. Work as one complete package; no micro-review gates.

## Phase 1H boundary
Reviewer verdict: **Proceed with safeguards** on the versioned loader at QSD `staging` SHA `c706f69a92a2f53a439e38aab3998dac1bd39ae8`. Source, remote branches and staging deploy were inspected; CI `38867863037` reportedly passed. Owner's authenticated screenshots show QSD Profile Logo rendered in WEX Studio. Network/SRI browser instrumentation remains unverified; do not invent that evidence. Expanded mobile Header padding is a separate WEX UI refinement, **not a delivery architecture blocker**. Earlier evidence retained in this file's Git history. No production promotion authorised.

## Accepted upstream producer — WEX Phase 2E
Reviewer audited WEX `main` SHA `1cd438e5826307a6b00a65e7b85dc01fbb475377`; its Pages run `38994010321` completed successfully including live pointer/manifest verification. WEX has accepted ADR 0025 (studio staging pointer); publicly served:
`https://codebynath.github.io/WEXdesigns/studio-releases/staging-pointer.json`
Schema `wex-studio-staging-pointer@1`, contract `wex-studio-host@1`. Current pointer: `sequence:1`, `change:advance`, `releaseId:wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d`, `manifestPath:<releaseId>/manifest.json`, `manifestDigest:sha384-TGJL9R8YXT5xSlCTXoBG8NtbtuBnbXUKEZiqzlU5MBnGuoIgoREBquPzbO7Cob7W`, `previous:null`. The immutable release remains retained. This paragraph supplies the cross-repo contract: **QSD Builder must not read/clone/fetch/search or modify WEX repository**. Request missing facts from Reviewer.

## QSD Builder implementation package
1. Work only in `CodeByNath/qldscubadive`. First audit QSD `main` Code Map `docs/code-map/studio-host.md`, architecture/rules, deployed `staging` source `StudioReleaseLock.php` and `StudioAssetGateway.php`, tests and deployment workflow. Four branches are currently present: permanent `main`, `staging`, `Project-work-instructions`, plus `wex-studio-staging-package`. **No fifth branch**; inspect existing topic/heads and reuse safely or complete authorised safe housekeeping via Git-capable Builder before opening another.
2. On **staging only**, replace the hardcoded runtime pin selection with server-side fetch of the fixed, allowlisted HTTPS WEX staging-pointer endpoint. No browser-supplied URL/version, cross-origin redirect, generic proxy, secrets or new QSD data API. Strictly validate canonical pointer schema, exact keys, supported contract, release-ID format, derived relative `manifestPath`, digest shape, sequence/previous chain. Maintain durable accepted `sequence`/release identity sufficient to resist replay across requests/restarts. Enforce bounded revalidation/cache (≤300 seconds), preserve an independently verified last-known-good release on outage/tamper, never serve unverified assets. Consider first-run bootstrap and legitimate skipped intermediate sequences; do not silently weaken replay checks.
3. Reuse existing manifest SHA-384/inventory/path validation, SRI/CORS attributes, QSD permission gate, `QSDStudioConfig`, pre-module `profile.logo` binding and QSD-owned REST/media logic. No WEX CSS/UI changes, no changes to production fixed release pin. Record explicit rollback and recovery operations. If server environments cannot be reliably distinguished, stop at that real deployment safety gate rather than unintentionally enabling auto-update on production.
4. Add deterministic tests for initial pointer, successful approved advance **without any QSD code commit**, rollback, cache/restart, invalid hashes/schema/paths, replay/downgrade, redirect/origin failure, pointer/Pages outage, last-known-good and auth/Logo regression. Run required checks; deploy **QSD staging only**. Verify actual requests/load from WEX Pages and runtime Logo handoff; visual layout refinement is out of scope. Demonstrate release selection can change via approved WEX pointer alone, no QSD redeploy.
5. Update THIS file to `AWAITING REVIEWER REVIEW` with exact pushed SHA, branch, tests, CI/deployment and honest runtime evidence. Stop. Reviewer independently checks entire package once.

## Scope/authority
No WEX repository access by QSD Builder; no changes to WEX release producer or ADR. No production update, no unverified mutable JS/CSS, no local asset copying, no Profile identity/data changes. Keep this one active work file, with historical details available in Git history.
