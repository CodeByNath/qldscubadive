# QSD Studio — versioned WEX consumer

Status: BUILDER ACTION REQUIRED — WEX release verified; begin QSD consumer package
Phase: 1H — consume approved WEX version on staging
Actor: BUILDER:CODER (Claude) — execute authorised Phase 1H package.

## Upstream dependency resolved — 16 October 2026

WEX Phase 2B accepted on `main` `e45a48d15a44436a2b9474c4f6d527ff7948cc5d` and public release verification recorded. Pages run `38860174506` succeeded. Immutable release `wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d` (source `7d1dd544fb92f5d019e5034cd15ede94857b671d`, contract `wex-studio-host@1`) is available at:

https://codebynath.github.io/WEXdesigns/studio-releases/wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d/manifest.json

Manifest SHA-384: `sha384-TGJL9R8YXT5xSlCTXoBG8NtbtuBnbXUKEZiqzlU5MBnGuoIgoREBquPzbO7Cob7W`.

WEX closeout records verified 74 runtime-file digests, MIME/CORS, cross-origin module and font rendering, and retention. This is the approved release to pin for QSD staging. **Do not replace it with the earlier `ec445a7` source build or a mutable latest URL.** WEX release's source SHA differs from its later producer/promotion commit by design. Fallback/rollback reference is the existing plugin-local bundle. No production deployment authorised. Existing QSD topic branch must be inspected before reuse; preserve unrelated commits. QSD may now execute all package steps below without another planning gate, stopping only for a concrete security/architecture conflict.

## Owner authorisation — 16 October 2026
Replace repeated manual WEX bundle-copy / QSD asset commits with an approved version-pinned delivery path, starting with fixed 64×64 Logo. This is one QSD integration package with internal steps, not approval per micro-fix. Existing Phase 1G manual refresh is cancelled. QSD `main` Code Map `docs/code-map/studio-host.md` and `StudioAssetGateway.php` are controlling source: the current loader only accepts plugin-local `studio-assets/manifest.json`, validates safe relative paths using realpath, adds filemtime URLs and provides `globalThis.wexStudioHostOptions` before module execution. Keep the real QSD Profile Logo and access gate.

## Work package (upstream accepted; execute now)
1. Read the accepted WEX release ADR/manifest/compatibility contract and artifact URL+digest. Audit QSD Studio gateway and deployment/CSP before edits. Reuse existing `wex-studio-staging-package` topic if safe; four remote branch cap, never remove permanent branches.
2. Add the smallest QSD-owned **server-controlled release lock** for exact approved immutable WEX release and expected manifest digest. No browser-controlled selectors, remote arbitrary URL, generic API, credentials in client, or fallback to unverified assets. Validate scheme/origin, manifest, paths, digest inventory and compatibility. Handle fetch/validation failures closed. Enqueue external module/CSS with compatible SRI/CORS/CSP where proven; nested font assets need controlled verified origin. Preserve pre-module Logo binding, permission gating, old /station/ and unrelated admin.
3. Unit/integration/security tests: unsafe paths/URL, wrong hashes, incompatible contract, missing release, CSP/CORS failure, integrity, no secrets, no regression to Profile/Logo. Exercise release pin update and rollback to retained approved version without copying WEX assets. Run required QSD checks.
4. On **staging only**, select the verified immutable WEX release ID above (whose fixed Logo derives from accepted WEX source), push under QSD workflow, deploy, inspect authenticated `/studio/` DOM at collapsed/expanded/hover/compact sidebar: Logo wrapper exactly 64×64, source image proportional/no overlap, keyboard/focus/fallback intact. Report exact QSD SHA, manifest/release hash, CI/deployment and browser evidence. If authenticated browser unavailable, say unverified; never call CI live proof.
5. Update THIS SAME file to `AWAITING REVIEWER REVIEW` with complete one-package evidence and stop. Reviewer reviews whole QSD package once.

## Hard boundaries
No production deployment, no automatic adoption of WEX `main`/`latest`, no WEX presentation/CSS in QSD, no Profile/Account media/data/identity changes, no replacement of QSD host framework, no secrets exposed. Stop only for concrete unsafe/unavailable dependency or genuine architecture/security decision outside approved contract. Do not claim new delivery mechanism works until WEX artifact and QSD runtime evidence exist.

WEX WEXdesigns actor is Codex; QSD actor is Claude. Repositories and branches remain independent. Historical QSD Phase 1F/1G evidence is in this file's Git history.
