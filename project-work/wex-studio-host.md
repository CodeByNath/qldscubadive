# QSD Studio — versioned WEX consumer

Status: BUILDER ACTION REQUIRED — DEPENDENCY WAIT (WEX verified release)
Phase: 1H — consume approved WEX version on staging
Actor: BUILDER:CODER (Claude); commence implementation only after WEX release contract and approved artifact are accepted.

## Owner authorisation — 16 October 2026
Replace repeated manual WEX bundle-copy / QSD asset commits with an approved version-pinned delivery path, starting with fixed 64×64 Logo. This is one QSD integration package with internal steps, not approval per micro-fix. Existing Phase 1G manual refresh is cancelled. QSD `main` Code Map `docs/code-map/studio-host.md` and `StudioAssetGateway.php` are controlling source: the current loader only accepts plugin-local `studio-assets/manifest.json`, validates safe relative paths using realpath, adds filemtime URLs and provides `globalThis.wexStudioHostOptions` before module execution. Keep the real QSD Profile Logo and access gate.

## Work package (do not start before upstream acceptance)
1. Read the accepted WEX release ADR/manifest/compatibility contract and artifact URL+digest. Audit QSD Studio gateway and deployment/CSP before edits. Reuse existing `wex-studio-staging-package` topic if safe; four remote branch cap, never remove permanent branches.
2. Add the smallest QSD-owned **server-controlled release lock** for exact approved immutable WEX release and expected manifest digest. No browser-controlled selectors, remote arbitrary URL, generic API, credentials in client, or fallback to unverified assets. Validate scheme/origin, manifest, paths, digest inventory and compatibility. Handle fetch/validation failures closed. Enqueue external module/CSS with compatible SRI/CORS/CSP where proven; nested font assets need controlled verified origin. Preserve pre-module Logo binding, permission gating, old /station/ and unrelated admin.
3. Unit/integration/security tests: unsafe paths/URL, wrong hashes, incompatible contract, missing release, CSP/CORS failure, integrity, no secrets, no regression to Profile/Logo. Exercise release pin update and rollback to retained approved version without copying WEX assets. Run required QSD checks.
4. On **staging only**, select approved WEX release at fixed Logo SHA `ec445a73dc98b94f1781096e94ccc34e48bff32c`, push under QSD workflow, deploy, inspect authenticated `/studio/` DOM at collapsed/expanded/hover/compact sidebar: Logo wrapper exactly 64×64, source image proportional/no overlap, keyboard/focus/fallback intact. Report exact QSD SHA, manifest/release hash, CI/deployment and browser evidence. If authenticated browser unavailable, say unverified; never call CI live proof.
5. Update THIS SAME file to `AWAITING REVIEWER REVIEW` with complete one-package evidence and stop. Reviewer reviews whole QSD package once.

## Hard boundaries
No production deployment, no automatic adoption of WEX `main`/`latest`, no WEX presentation/CSS in QSD, no Profile/Account media/data/identity changes, no replacement of QSD host framework, no secrets exposed. Stop only for concrete unsafe/unavailable dependency or genuine architecture/security decision outside approved contract. Do not claim new delivery mechanism works until WEX artifact and QSD runtime evidence exist.

WEX WEXdesigns actor is Codex; QSD actor is Claude. Repositories and branches remain independent. Historical QSD Phase 1F/1G evidence is in this file's Git history.
