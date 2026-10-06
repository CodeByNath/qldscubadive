# Security Phase 2 — real runtime and controlled provider validation

Status: BUILDER ACTION REQUIRED
Phase: Security Phase 2 — Owner-corrected credential flow package
Actor: Builder

## Accepted baseline

Security Phase 1 is accepted on `main` at `da934936edafcf892ebab33e870e6f5f511d147f` with Reviewer verdict `Proceed with safeguards`.

Phase 2 must complete before any Phase 3 Service Manager / Service Element work begins.

## Authority/read order

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md`
4. `docs/code-map/settings-station.md`
5. `docs/architecture/credential-broker-contract.md`
6. authoritative Settings/Connections/Security source
7. `skills/qsd-platform-architecture/SKILL.md` if any new identity/entity boundary appears

## Phase 2 safeguards — binding

1. Any `qsd/v1` broker flow must derive WordPress user identity and caller/component identity server-side. Client input may request operation/subject only; it must not choose another user id or invent caller authority.
2. Credential storage, request-key claim/consume/replay/expiry, and rotation/re-seal must be validated on a real WordPress + real database runtime, not only fake-`$wpdb` tests.
3. Real key material, one real provider credential, and one real provider call are allowed only as controlled Phase 2 validation through the accepted broker boundary. Never commit, log, echo, project, or return secrets.
4. Service Element identity remains deferred to Phase 3. Do not reopen or implement it here.

## Builder package

Use one substantial topic branch.

- Add only the minimum governed `qsd/v1` validation flow required to exercise the broker end-to-end.
- Derive authenticated WordPress user and allow-listed caller identity server-side.
- Add the minimum provider validation scope/operation needed for a connection/authentication test. Do not add importer/product mapping behavior.
- Validate encrypted credential save/read state against real WordPress option storage.
- Validate request-key issue → atomic consume → replay rejection → expiry/binding rejection against the real database.
- Validate key rotation/re-seal on real stored encrypted credentials, including fail-closed behavior.
- Perform one controlled real provider call through the broker and record only safe result metadata.
- Update the credential-broker contract, Settings Code Map and roadmap only where Phase 2 reality changes current authority.
- Preserve deterministic tests and run `npm test` and `npm run docs:check`.

## Exit gate

Phase 2 is not complete until Reviewer has evidence for:
- server-derived user/caller identity;
- real WordPress/database storage and broker consume behavior;
- real rotation/re-seal behavior;
- no-secret REST/storage/audit output;
- one controlled provider call through the broker;
- exact pushed SHA and deterministic validation.

## Hard exclusions / stop gates

Stop rather than guess for any new Platform ID family, destructive migration/purge, production deployment, widened deployment path, Service Element identity decision, importer/mapping work, or provider behavior beyond the minimum controlled validation operation.

No staging or production deployment unless separately authorised by the Owner.

## Handoff

Push the exact topic SHA, update this same file to `AWAITING REVIEWER REVIEW` with runtime evidence and redacted/safe provider-call evidence, then stop. Do not begin Phase 3.

## Builder preflight — 2026-10-06 (capability gap, no source change)

Status at time of preflight: `BUILDER ACTION REQUIRED` (superseded by the Owner-directed handback below). Per `AGENTS.md` *Executor capability preflight*, the phase was not partially advanced: no topic branch opened, no source changed, `main` still `da934936edafcf892ebab33e870e6f5f511d147f`.

Mandatory operations vs. this execution surface (Owner's macOS workstation, Claude Code):

| Required operation | Available? |
|---|---|
| Git fetch/branch/commit/push, CI inspection | yes |
| PHP 8.x with sodium + mysqli/pdo_mysql | yes (PHP 8.5.6) |
| Real WordPress install loading `qsd-platform` | **no** — none present |
| Real database server (MySQL/MariaDB) | **no** — no server, no Docker, no Local/MAMP |
| WP-CLI (needed for `wp qsd credentials reseal` on a real runtime) | **no** |
| Real `QSD_CREDENTIAL_KEY` / `_PREVIOUS` in a non-committed wp-config | **no** (not to be provisioned without a runtime) |
| One real Rezdy credential (only declared provider is `rezdy`) | **no** — none supplied |
| Approved Rezdy target for the one validation call (staging vs. live API) | **not stated** |

Required execution surface / Owner inputs before Builder can run the phase end-to-end:

1. **Runtime — RESOLVED.** Use the existing QSD staging2 WordPress runtime. Do not stand up a separate local WordPress/database stack.
2. **Credential** — Owner places one Rezdy API key directly into the runtime (Settings UI as a `manage_options` user, or a non-committed local file Builder reads only into the runtime). It must not be pasted into chat, the work file, a commit, or a log.
3. **Target** — Owner confirms which Rezdy endpoint the single connection/authentication call may hit (Rezdy staging API preferred) and that one read-only authentication call is acceptable.

Once 1–3 are in place, the Builder package in this file is executable as written; no scope question is open.

## Note to Reviewer — Owner-directed handback (2026-10-06)

At the Owner's direction, Builder returns this file to Reviewer as `AWAITING REVIEWER REVIEW`. **No candidate is submitted for review**: there is no topic branch, no new SHA, and `main` is unchanged at `da934936edafcf892ebab33e870e6f5f511d147f`.

Reviewer decision requested:

1. **Runtime surface.** Accept option (a), a disposable local MariaDB + WordPress + WP-CLI runtime on the Owner workstation, or name another non-production runtime. If neither, say whether the Phase 2 exit gate's "real WordPress/database" evidence may come from a different surface.
2. **Provider call.** Confirm that the single controlled call is a read-only Rezdy authentication/connection check against the **Rezdy staging API**. Also confirm how the Owner supplies the key: entered directly into the runtime, never in chat, the work file, a commit or a log. If the live call should be deferred or descoped, re-issue the exit gate.
3. **Reassignment.** Once (1)–(2) are settled and the Owner has supplied the credential, set this file back to `BUILDER ACTION REQUIRED`. Builder will then run the package as written, and no other scope question is open.


## Reviewer decision — Phase 2 execution surface

Verdict: Proceed with safeguards

The Builder preflight is accepted as a capability finding, not an implementation failure. No source candidate existed and `main` remains unchanged.

Builder is authorised to continue Phase 2 using the **existing QSD staging2 WordPress runtime as the normal real-runtime validation surface**.

Canonical runtime:
- WordPress host: `staging2.qldscubadive.com.au`
- QSD Admin Station: `/station/`
- deployment path is the repository's existing GitHub Actions staging workflow only;
- deployment target remains exactly `/home/customer/www/staging2.qldscubadive.com.au/public_html`;
- the workflow may write only the already-authorised `wp-content/plugins/qsd-platform/` and `wp-content/themes/qsd-shell/` destinations.

Do **not** create a second/local WordPress + database stack for this phase unless the Owner later explicitly authorises that fallback.

Provider validation is bounded to **one read-only Rezdy authentication/connection check against the existing Rezdy staging base URL** `https://api.rezdy-staging.com/v1/`. This does not authorise product import, mapping, mutation, booking changes, or production Rezdy calls.



### Staging2 deployment authorisation for this Phase 2 package

For this active Phase 2 package, the Owner authorises Builder to deploy an approved Phase 2 candidate to **staging2** through the repository's existing GitHub Actions staging deployment when runtime/browser evidence is required.

Builder may therefore:
1. implement on the authorised topic branch;
2. run deterministic validation;
3. hand off the exact candidate SHA for Reviewer review;
4. after Reviewer/Owner approval for that candidate, promote that exact approved SHA to the existing `staging` deployment boundary;
5. let the existing GitHub Actions workflow deploy it to staging2;
6. validate the real `/station/` UI, `qsd/v1`, credential broker, encrypted WordPress storage, request-key behavior, audit and permitted provider check there.

This is **not** standing permission to deploy arbitrary/unreviewed changes. Deployment requires the candidate being approved for staging use. Do not alter the workflow, destination, SSH scope, `--delete` scope, production site, or hosting paths.

Production deployment remains prohibited.

The database on staging2 is runtime/storage evidence only. Do not introduce direct SQL/query application paths or bypass the existing QSD API, broker or owning stores.

### Credential handling safeguard

The Owner supplies the Rezdy staging API key **directly into the local runtime** (for example through the Settings UI while logged in with administrator authority, or another non-committed local secret input). The key must never be pasted into chat, this work file, source, tests, command output, CI, or logs.

### Runtime evidence required at handoff

The Builder must provide safe evidence for:

- real WordPress option storage containing only encrypted envelopes, not plaintext;
- real database request-key issue/claim/atomic consume/replay/expiry/binding behavior;
- server-derived WordPress user and allow-listed caller identity;
- real key rotation/re-seal and fail-closed behavior;
- REST and audit outputs containing no key, hash, or provider secret;
- one successful or safely classified failed read-only Rezdy staging connection/authentication call through the broker;
- deterministic `npm test` and `npm run docs:check`;
- exact topic SHA.

Do not expose the credential to prove any of the above. Redacted/safe metadata is sufficient.

Phase 3 remains blocked until Phase 2 receives Reviewer acceptance. Service Element identity remains out of scope.


## Owner-approved Station plan tree

Preserve this hierarchy exactly in Phase 2 and carry it into `docs/roadmap.md` when the Phase 2 topic branch is opened:

```text
Services Station
├─ Details
├─ Connections
└─ Settings
   ├─ General
   ├─ Tools
   │  ├─ Rezdy importer
   │  ├─ Stripe-related tools
   │  └─ future operational tools
   └─ Security
      ├─ API keys
      ├─ credentials
      ├─ encryption
      ├─ request-key broker
      ├─ permissions
      ├─ rotation/re-seal
      └─ audit
```

Ownership meaning:
- `Services Station → Connections` remains Service-domain relationships.
- `Settings → Tools` contains operational tools/importers such as Rezdy.
- `Settings → Security` owns API keys, credentials and the credential-security system.
- Tools consume Security-governed authority; Tools do not own/read long-lived credentials directly.
- Do not reintroduce a separate “Settings Station” or collapse Tools and Security into a “Connections/Security” UI concept.


### Reusable Settings-tab pattern

`Settings` is a reusable tab pattern inside a Station, not a separate Station.

For any Station that needs it:

```text
Station
└─ Settings
   ├─ General
   ├─ Tools
   └─ Security
```

Rules:
- `General` may surface global/platform settings relevant to that Station and may also contain Station-owned settings where appropriate.
- `Tools` contains operational tools owned/used by that Station.
- `Security` may surface global Security capabilities relevant across Stations.
- Global General/Security data is owned once by the platform authority; presenting it in multiple Stations must not duplicate persistence or create parallel settings/security systems.
- Screen placement never transfers persistence or domain authority.
- A Station may omit any Settings subsection it does not need.


## Owner UI stop gate

The authoritative roadmap/code-map correction for the Settings/Security work is prepared on topic branch `docs/settings-security-roadmap`.

Builder must follow the phased path recorded there:

1. Phase 2A — real runtime/API/security validation.
2. Phase 2B — place Settings inside Services Station as `General | Tools | Security`.
3. Phase 2C — build `Settings → Security → API Keys` UI.
4. **STOP at browser-ready UI.**
5. Phase 2D — rotation operator flow only after explicit Owner UI acceptance.
6. Phase 2E — closeout, then Reviewer acceptance before Phase 3.

At the Phase 2C stop:

- set this file to `BLOCKED — OWNER UI REVIEW REQUIRED`;
- notify the Owner that the API Keys Security UI is ready;
- record exact candidate SHA and browser/runtime surface;
- do not continue implementation;
- do not treat `run the cycle`, `continue the work`, `review the latest work`, or equivalent as permission to cross this gate.

Only an explicit Owner acceptance/correction of the UI unlocks Phase 2D.

The approved presentation model remains:

```text
Services Station
├─ Details
├─ Connections
└─ Settings
   ├─ General
   ├─ Tools
   │  ├─ Rezdy importer
   │  ├─ Stripe-related tools
   │  └─ future operational tools
   └─ Security
      ├─ API keys
      ├─ credentials
      ├─ encryption
      ├─ request-key broker
      ├─ permissions
      ├─ rotation/re-seal
      └─ audit
```

Security owns provider API credentials. Tools such as the Rezdy importer consume Security-governed authority and must never own/read the long-lived credential.


## Builder handoff — Phase 2A candidate (2026-10-06)

**What this handoff is.** The Phase 2A code candidate, submitted for Reviewer review and **approval to promote to `staging`** under the staging2 authorisation above (steps 3–4). This is **not** the Phase 2 exit gate. No staging2 runtime evidence exists yet because nothing has been deployed. Phases 2B and 2C have not started.

- **Branch:** `docs/settings-security-roadmap`. It is the single permitted topic branch, so Builder continued on the Reviewer's prepared docs branch rather than opening a second one.
- **Candidate SHA:** `075a3d4189e00ccd5cd057decfeacc223d2045fb`. Remote verified with `git ls-remote`.
- **Base:** `5a197a6` (Reviewer docs) on top of `main` `da934936edafcf892ebab33e870e6f5f511d147f`; `main` is an ancestor. Diff vs `main`: 20 files, +1158 / −104. That includes the Reviewer's two docs commits.
- **`main` / `staging`:** unchanged (`da93493` / `69f68ca`). No deploy, no provider call, no credential and no key were used.

### Changes

| Area | Change |
|---|---|
| Caller identity (safeguard 1) | `CredentialBroker` takes a server-side caller allow-list (caller → `provider:scope`). A caller that is not listed is refused with the new reason `unknown_caller`, and a broker with no list issues nothing. The list lives in `SettingsModule::brokerCallers()`, and its one entry is `settings.security-validation` → `rezdy:connection.verify`. |
| Provider scope (minimum) | `RezdyConnector` declares only `connection.verify`. New `Connectors/RezdyConnectionCheck.php` sends one `GET https://api.rezdy-staging.com/v1/products?limit=1` (the `apiKey` query parameter is Rezdy's documented auth) with no redirects and a 10 s timeout. It reads only the HTTP status and `requestStatus.success`, and the body is discarded. It returns `{provider, environment, outcome, http_status, latency_ms}`. Outcomes: `authenticated`, `unauthorized`, `rate_limited`, `upstream_error`, `unexpected_response`, `network_error`. **Phase 2 bound enforced in code:** an environment other than `staging` returns `refused_environment` with no request. |
| Governed `qsd/v1` flow | New `Security/BrokerValidation.php` and `Http/SettingsSecurityController.php`: `POST qsd/v1/admin/settings/security/broker-validation`, gated by `manage_qsd` + `manage_options`. User = `get_current_user_id()`; caller = the constant. The request is never read, so a client-supplied `user_id`, `caller`, `provider` or `subject` is ignored (tested). |
| Validation run | One run, on the real install, against the real option and `$wpdb` stores:<br>(1) the encryption key is present;<br>(2) each Rezdy secret is a `{v,alg,kid,nonce,ct}` envelope that opens under the current key, and its plaintext is absent from the stored option;<br>(3) rotation readiness via the new read-only `CredentialRotation::inspect()`;<br>(4) issue → row stored by hash only, bound to provider/scope/caller/user/per-run subject, TTL 60;<br>(5) consume + perform, the **single provider call**;<br>(6) replay refused;<br>(7) a TTL-1 key refused as expired after a real 2 s wait;<br>(8) an abandoned TTL-1 key swept;<br>(9) wrong binding refused and the key burned;<br>(10) zero rows left;<br>(11) the run's audit events are recorded, and the audit holds no key, hash or secret.<br>The report is leak-guarded against every issued key, key hash, decrypted secret and the key id before it is returned. |
| Browser trigger | The Settings Connections lane shows administrators a **Security check** panel with the button "Run security check". It sends a bodyless POST and renders pass/fail rows plus the provider outcome line. It is hidden for platform managers. This is the minimum UI needed for the Owner to run the check on staging2 (Builder has no browser), and it is transitional until Phase 2B/2C. |
| Rotation | Re-seal is unchanged and still shell-only. `inspect()` is read-only, and the contract now asserts that only `CredentialRotationCommand` calls `->reseal(`. |
| Docs | `credential-broker-contract.md` (status, Issue step, Permission, Current boundaries), the roadmap 2A status, Settings `CLAUDE.md`, and the Settings Code Map. |

### Deterministic validation (local, PHP 8.5.6 / Node, at `075a3d4`)

- `npm test` → **exit 0**: typecheck, every PHP test, Vite build, JS 24/24, docs check.
  - `tests/settings-security-validation.php`: **40/40** (new).
  - `tests/settings-credential-broker.php`: 65.
  - `tests/settings-connections.php`: 54.
  - `tests/settings-credential-rotation.php`: 25.
  - `contract:settings-station`: 50.
  - `regression:settings-home`: 41 (adds sections 9–10 for the Security check).
- `npm run docs:check` → passed: 46 Markdown files, 19 Code Maps.
- CI does not run on topic-branch pushes; it will run on promotion to `staging`.

### Deviations / flags for Reviewer

1. **Code Map rewritten for length.** The Reviewer's `5a197a6` version of `docs/code-map/settings-station.md` failed `docs:check` at 751 prose words against the 600 limit, so the docs check was red on the branch before Builder touched it. Builder kept the Owner tree, the ownership rules and the Owner UI gate verbatim in meaning, and moved the 2A–2E phase detail to a pointer to `docs/roadmap.md`, which carries it in full. The map is now within the limit.
2. **Settings `CLAUDE.md` boundary changed.** It used to say "no brokered scopes or provider HTTP calls on RezdyConnector before the importer review". It now allows only `RezdyConnectionCheck` / `connection.verify`, which this work file authorises, and adds "never take a grant's user or caller from client input".
3. **Rezdy auth uses the `apiKey` query parameter** (Rezdy v1). QSD never logs, returns or stores the URL, and WordPress HTTP does not log requests by default. The key will appear in Rezdy-side request logs, which is inherent to Rezdy's API.
4. **Concurrent-race proof stays deterministic.** On the runtime, atomic single use is shown by the real `DELETE` affecting one row (consume, then replay refused). A true two-process race is covered by the existing `StaleReadStore` test only.
5. **Permission choice.** The validation route requires administrator authority, not just `manage_qsd`, because it spends the stored credential. Relaxing that for Tools is a Phase 3 decision.

### Runtime plan once approved (staging2), with Owner-only steps marked

1. Reviewer/Owner approves `075a3d4` for staging. Builder pushes that exact SHA to `staging` (fast-forward check first), and the existing workflow deploys it. Builder verifies the `test` and `deploy-staging` jobs.
2. **Owner:** add `define('QSD_CREDENTIAL_KEY', '<base64 32 bytes>');` to staging2 `wp-config.php` (the workflow cannot write it, and Builder has no host shell). Generate the key with `php -r "echo base64_encode(random_bytes(32));"`, and never paste it anywhere.
3. **Owner:** in `/station/` → Settings → Connections, set Rezdy environment to *Staging (sandbox)* and enter the Rezdy staging API key. Save.
4. **Owner:** click **Run security check**. Report back only the on-screen pass/fail rows and the Rezdy outcome line (no secret is shown), or a screenshot. Optionally check DevTools → Network for the `broker-validation` and `connections` JSON bodies (safe).
5. **Owner, rotation on real storage** (host shell, which Builder lacks):
   1. Move the key to `QSD_CREDENTIAL_KEY_PREVIOUS` and add a new `QSD_CREDENTIAL_KEY`.
   2. Run the check again. Expected fail-closed result: rotation shows `previous: ["rezdy:api_key"]`, Rezdy reads as not configured, and no provider call is made.
   3. Run `wp qsd credentials reseal`. The output is slot names and counts only.
   4. Run the check again. Expected: passes, `current: ["rezdy:api_key"]`.
   5. Remove `_PREVIOUS`.
6. Builder records the safe evidence here and hands off for the Phase 2A exit review.

Builder stops here. Phase 2B/2C, any staging promotion and Phase 3 are not started.


## Reviewer decision — Phase 2A handoff

Verdict: Proceed with safeguards

Reviewed exact candidate `075a3d4189e00ccd5cd057decfeacc223d2045fb` against `main`.

The candidate correctly keeps the Rezdy provider credential behind the existing QSD API/security boundary: the browser submits it as a write-only secret through `qsd/v1`; the server seals it before storage; projections return configured state only; the provider operation receives it only inside the server-side broker.

### Required correction — separate platform key from provider API keys

The handoff/runtime instructions currently blur two different secrets and wrongly assign a backend task to the Owner/admin user.

1. **Platform master encryption key**
   - `QSD_CREDENTIAL_KEY` is infrastructure key material used by `CredentialCipher`.
   - It is not a Rezdy/API/provider key.
   - It is never entered or managed by a normal QSD admin user.
   - It stays outside the database and outside the Admin Station.
   - Provisioning/rotation is an operator/deployment responsibility, invisible to the business admin.
   - Do not require the business admin/Owner to edit `wp-config.php` as part of normal Security/API-key setup.
   - Do not widen the GitHub deployment path to edit `wp-config.php` without a separate explicit Owner approval.

2. **Provider credentials (Rezdy, Stripe, future providers)**
   - These belong in `Services Station → Settings → Security → API Keys`.
   - A non-technical authorised administrator enters/replaces/removes them only through the QSD UI.
   - They travel through `qsd/v1` to the Security backend, are encrypted before persistence, and are never readable back through UI/API.
   - Tools/importers consume brokered authority; they never ask the user to place provider keys in config files or backend/server files.

### Builder correction

Before staging promotion:

- rewrite the Phase 2A runtime/operator instructions and affected roadmap/contract wording to make the two secret classes above explicit;
- remove every instruction that asks the business admin/Owner to manually place provider credentials or routine Security configuration in `wp-config.php`/host backend;
- keep `QSD_CREDENTIAL_KEY` as platform infrastructure only;
- if staging2 lacks the platform master key, report `BLOCKED — INFRASTRUCTURE KEY PROVISIONING REQUIRED` with the narrow operator requirement. Do not push that responsibility into the Security UI and do not invent a new deployment/config path;
- preserve the existing write-only provider-key UI/API behavior already present in the candidate;
- preserve the Owner UI gate at Phase 2C.

After this documentation/workflow correction, push a new exact candidate SHA and return for Reviewer. Do not deploy staging yet.


## Builder correction handoff — two secret classes (2026-10-06)

- **Candidate SHA:** `68a95c27d1a52af13f10bcd946abea4139383cc3` on `docs/settings-security-roadmap`. Remote verified. Parent: `075a3d4`.
- **Correction diff:** 7 files, +38 / −12, documentation and wording only. No PHP behaviour changed, and the write-only provider-key UI/API is untouched.
- **`main` / `staging`:** unchanged (`da93493` / `69f68ca`). Not deployed.

### What changed

| File | Change |
|---|---|
| `docs/architecture/credential-broker-contract.md` | New **Two secret classes** section, a table plus rules. The **platform master key** (`QSD_CREDENTIAL_KEY` / `_PREVIOUS`) is infrastructure used only by `CredentialCipher`: operator-provisioned outside the database and the Admin Station, no UI, REST or deployment-workflow path, and never a business-admin task. **Provider credentials** (Rezdy, Stripe, future providers) are entered, replaced and removed only by an authorised administrator through the QSD UI (target `Services Station → Settings → Security → API Keys`), go write-only through `qsd/v1`, and are never readable back. Rules: no admin is ever asked to edit `wp-config.php` or any server file; provider keys never go in config or server files; the deployment workflow does not write server config without separate Owner approval; when the key is missing, the UI only reports it as an operator setup step. *Encryption at rest* and *Key operations* are relabelled as operator tasks. *Real credentials* now separates the operator (master key) from the administrator (Rezdy key via the UI). |
| `docs/roadmap.md` | 2A carries a "Two secret classes" paragraph that links to the contract. |
| `docs/code-map/settings-station.md` | The cipher bullet names the master key as operator-provisioned and never managed in the UI, while provider keys go through the UI only. Still within 600 words. |
| `src/Modules/Settings/CLAUDE.md` | Same distinction, plus the boundary "never ask a user to put a provider key or Security setup in `wp-config.php` or any server file". |
| `SettingsConnectionsLane.tsx`, `useSettingsConnections.ts` | The no-key warning used to say "Ask the site administrator to configure it". It now says "Secure credential storage is not set up on this server yet, so API keys cannot be saved. This is a one-time platform setup step for the platform operator, not something to configure here." It names no file or constant. |
| `scripts/settings-home-regression.mjs` | Asserts the new warning, and that the lane never shows `wp-config` or `QSD_CREDENTIAL_KEY` (42 checks). |

### Superseded runtime plan

This replaces the plan in the Builder handoff dated 2026-10-06 above. That plan's steps 2 and 5, which asked the Owner to edit `wp-config.php` and run shell rotation, are withdrawn as Owner/admin steps.

1. After Reviewer/Owner approval of `68a95c2` for staging, Builder pushes that exact SHA to `staging`, and the existing workflow deploys it. The workflow and its paths are unchanged.
2. **Platform master key check.** Once deployed, the Settings lane or `GET admin/settings/connections` → `encryption.available` shows, safely, whether staging2 has the master key. Builder has no host access and cannot see this before deploying. **If it is absent**, Builder sets this file to `BLOCKED — INFRASTRUCTURE KEY PROVISIONING REQUIRED` with this narrow operator requirement and nothing more:
   - define `QSD_CREDENTIAL_KEY` (base64 of 32 random bytes) in the staging2 server configuration, outside the database and the repo;
   - this is done by whoever operates the SiteGround hosting, and is not an Admin Station or business-admin task;
   - no new deployment or config path is added.
3. **Administrator, through the UI only:** in `/station/` → Settings, set Rezdy environment to *Staging (sandbox)*, enter the Rezdy staging API key (write-only) and save. Then click **Run security check** and share the on-screen pass/fail rows and the Rezdy outcome line. No secret is shown.
4. **Rotation on real storage** is a platform operator shell task (contract → Key operations). It is not an Owner or admin step: move the key to `_PREVIOUS` and set a new key, check the fail-closed state, run `wp qsd credentials reseal`, check again, then remove `_PREVIOUS`. Builder has no host shell. The Reviewer should decide how the rotation evidence is gathered: by the hosting operator, or deferred to Phase 2D with the readiness evidence from `inspect()` that the security check already reports.
5. Builder records the safe evidence and hands off for the Phase 2A exit review.

The Phase 2C Owner UI gate is unchanged. Not done: staging promotion, Phase 2B/2C, Phase 3.


## Reviewer decision — corrected Phase 2A candidate

Verdict: Proceed with safeguards

Reviewed exact correction candidate `68a95c27d1a52af13f10bcd946abea4139383cc3`.

The correction resolves the Owner/admin boundary:

- provider API keys are QSD UI inputs only and remain write-only through `qsd/v1`;
- the platform master encryption key is infrastructure, not a provider key and not a business-admin responsibility;
- the Admin Station does not expose `wp-config.php`, the master key, or server-file instructions;
- no PHP security behavior was widened by the correction;
- the existing Phase 2A candidate still preserves server-derived caller/user identity, broker-only provider-secret use, staging-only Rezdy verification, and no direct SQL/query application path.

### Next Builder action — stage exact candidate

Builder may now promote **exact SHA `68a95c27d1a52af13f10bcd946abea4139383cc3`** to the existing `staging` branch and let the existing guarded GitHub Actions workflow deploy it to staging2.

After deployment:

1. verify CI/test and `deploy-staging` succeeded on that exact SHA;
2. verify `https://staging2.qldscubadive.com.au/station/` loads the deployed Admin Station;
3. inspect the safe Security state through the QSD UI/API only;
4. if secure credential storage is available, the authorised administrator may enter the Rezdy staging API key through the QSD UI only and run the Security check;
5. if secure credential storage is unavailable, do **not** ask the administrator to edit backend files — set this work to `BLOCKED — INFRASTRUCTURE KEY PROVISIONING REQUIRED` and report only that the staging platform operator must provision the platform master key outside the database/UI.

For Phase 2A runtime evidence, collect only safe UI/API outcomes. Do not expose secrets, use direct SQL as an application path, alter the deployment workflow, or touch production.

### Rotation sequencing safeguard

Full destructive/operator key rotation/re-seal execution is **not required in this staging step**. Phase 2A may record read-only rotation readiness from `CredentialRotation::inspect()`. The actual operator re-seal exercise remains Phase 2D, after the mandatory Owner UI gate, unless the Owner separately authorises earlier infrastructure rotation.

After the staging2 validation evidence is recorded, return this file to `AWAITING REVIEWER REVIEW`. Do not start Phase 2B in the same handoff.


## Builder staging2 deployment evidence — `68a95c2` (2026-10-06)

Status stays `BUILDER ACTION REQUIRED`: Builder still owns finishing the 2A runtime evidence. The next step needs an authenticated administrator in the browser, and Builder has neither a browser nor a WordPress session.

**Promotion**
- Fast-forward check passed: `origin/staging` (`69f68ca`) is an ancestor of `68a95c27d1a52af13f10bcd946abea4139383cc3`.
- Pushed with `git push origin 68a95c2…:refs/heads/staging` (`69f68ca..68a95c2`); `git ls-remote` verified `staging` = `68a95c27d1a52af13f10bcd946abea4139383cc3`.
- The first attempt was blocked by Builder's local permission guard, not by the repository. The Owner then approved, and the identical push succeeded. Nothing else was changed in between.

**CI and deploy:** Actions run `37419196617` ("Test and deploy"), head `68a95c2`: `test` completed success, `deploy-staging` completed success. Workflow, paths and scope are unchanged.

**Live checks (unauthenticated, from Builder)**
- `https://staging2.qldscubadive.com.au/station/` → 200, redirected to `www.staging2…/station/` and titled "Admin Station".
- The deployed `wp-content/plugins/qsd-platform/dist/js/admin-station.js` contains the Security check and the `broker-validation` endpoint, so the new build is live.
- `GET qsd/v1/admin/settings/connections` → 401 `rest_forbidden`; `POST qsd/v1/admin/settings/security/broker-validation` → 401 `rest_forbidden`. Both routes are registered (not 404), and both refuse anonymous callers.

**Not yet known:** whether staging2 has the platform master key. `encryption.available` is visible only to an authenticated `manage_qsd` user.

**Next, needs an authorised administrator in `/station/` → Settings (QSD UI only)**
1. **If the lane shows** "Secure credential storage is not set up on this server yet…", report that and nothing else. Builder will then set `BLOCKED — INFRASTRUCTURE KEY PROVISIONING REQUIRED`: the staging platform operator must provision the platform master key outside the database and UI.
2. **Otherwise:**
   1. Under Rezdy, set Environment to *Staging (sandbox)*, enter the Rezdy staging API key in the write-only field and click **Save connection**.
   2. Click **Run security check**.
   3. Share the on-screen Passed/Failed badge, every ✓/✗ row and the "Rezdy (staging): …" outcome line, or a screenshot of the panel. None of it contains a secret.

Rotation: only the read-only `inspect()` readiness in that report is collected now. The operator re-seal stays in Phase 2D, as the Reviewer directed.


## Reviewer decision — staging2 Phase 2A evidence and sequencing correction

Verdict: Proceed with safeguards

The staging2 deployment evidence for exact SHA `68a95c27d1a52af13f10bcd946abea4139383cc3` is accepted for the boundaries it actually proves:

- guarded staging deployment succeeded;
- `/station/` is live;
- the deployed Admin Station bundle contains the Phase 2A Security validation code;
- the protected Settings/Security routes are registered and reject anonymous access;
- no deployment-path or production boundary was widened.

### Do not stop the Owner on the transitional Settings UI

The previous instruction asking the Owner to log into the current standalone/transitional Settings screen, enter the Rezdy key there, run the check, and report rows is superseded.

Reason: the approved product plan is to deliver provider API-key management at:

`Services Station → Settings → Security → API Keys`

The Owner/business administrator is non-technical and must interact with the finished QSD UI, not with temporary validation surfaces or backend/server configuration.

### Revised sequencing

Builder now proceeds directly with:

1. **Phase 2B** — migrate Settings presentation into `Services Station → Settings → General | Tools | Security`, preserving the existing Settings/Security backend and `qsd/v1` authority.
2. **Phase 2C** — build the browser-ready `Settings → Security → API Keys` surface with the approved write-only provider credential flow.

Do not ask the Owner to:
- use `wp-config.php`, SSH, WP-CLI, database tools or WordPress backend files;
- use the transitional standalone Settings/Connections screen for Phase 2 validation;
- manually inspect REST/DevTools responses;
- paste any provider key anywhere except the final QSD `Security → API Keys` UI.

### Phase 2A evidence deferred to the UI gate

The remaining authenticated runtime evidence that requires a real provider credential — encrypted save on staging2, brokered Rezdy staging call, safe audit/request-key runtime results, and safe `encryption.available` state — is deferred to the **Phase 2C Owner UI gate**.

This is a sequencing change only. The evidence is still required before Phase 2 can be accepted.

### Mandatory next stop

When `Services Station → Settings → Security → API Keys` is browser-ready and deployed on staging2:

- set this file to `BLOCKED — OWNER UI REVIEW REQUIRED`;
- notify the Owner that the actual Security/API Keys UI is ready;
- provide the exact candidate SHA and staging2 URL;
- stop;
- do not request a normal `run the cycle` to move past the gate.

At that gate the Owner may review the real UI and, if accepted, enter the Rezdy staging API key there. The resulting safe runtime validation can then satisfy the remaining Phase 2A evidence.

Builder may proceed with 2B/2C now. Do not start Phase 2D.


## Builder handoff — Phase 2B/2C candidate, before staging (2026-10-06)

**What this handoff is.** Code review of the 2B/2C candidate, **and a request for approval to deploy it to staging2** so the Owner UI gate can open. The Owner was asked whether to deploy straight away under the gate wording ("browser-ready and deployed on staging2") and chose **"Reviewer first"**. So, per the staging authorisation (deploy only an approved candidate), nothing has been deployed.

- **Candidate SHA:** `7b6864b3b7ca6c4584662cf6efc22b2896365f07` on `docs/settings-security-roadmap`. Remote verified; it fast-forwards `staging`.
- **Diff vs the staged `68a95c2`:** 32 files, +814 / −536.
- **`staging`:** still `68a95c2`, the 2A build. **`main`:** `da93493`.
- No credential was used, no provider call was made, and production was not touched.

### 2B — Services Station Settings placement

| Area | Change |
|---|---|
| Reusable pattern | New `station-manager/registry/stationSettings.ts`. Owners register contributions `{id, section: general\|tools\|security, stationIds, label, order, panel}`. `resolveStationSettings(stationId)` returns sections in General → Tools → Security order, omitting empty ones, with contributions sorted by `order`. `boot.ts` locks and enables it like the other registries. It imports no peer and no Admin Station. |
| Shared presentation | New `admin-station/presentation/StationSettings.tsx`. It renders a Station's sections on the shared `StationTabSet` and names no Station, panel or endpoint. |
| Services | `ServiceSettingsLane.tsx` now hosts `<StationSettings stationId="services">`. The two creation launchers moved unchanged into `ServiceCreateLaunchers.tsx`, which Service registers under General. The `ServiceLowerDeck` lanes (Details / Connections / Settings / Bin) are unchanged. |
| Settings panels | `settings-station/register.ts` now only contributes: General → *Service fields* (`ServiceMetaSchemaLane`, unchanged); Tools → *Rezdy importer* (`RezdyImporterTool`, which says "Not available yet" and that the importer will use the Security key through the server); Security → *API Keys*. |
| Retired standalone Settings | The Settings nav row, destination, `settings-home` source, `settings-deck` kit, the Admin binding, `SettingsDeck.tsx`, `SettingsConnectionsLane.tsx` and `useSettingsHome.ts` are removed. Every function they had is in the new placement, so the parity condition is met. Backend and `qsd/v1` routes are unchanged. |
| Boundaries | Service imports no Settings panel and Settings imports no Service peer; the contract asserts both. There is still one Settings backend, broker and API family. |

### 2C — Security → API Keys (`SecurityApiKeysPanel.tsx`)

Checked against the roadmap list:
- **Status lines.** "Secure storage: Ready" or "Not set up … one-time platform setup step for the platform operator". "Your access: Administrator" or "View only".
- **Provider card.** Name, description, environment select (saves on change), and state badge (Not set up / Incomplete / Ready).
- **API key row.**
  - State reads *Saved* or *Not added*.
  - **Add/Replace** opens a password input (`autocomplete=new-password`), with Save and Cancel. The input exists only while adding, is dropped on save, and the key is never rendered.
  - **Remove** asks inline ("… stops working until a new one is added") and then sends `clear`.
- **Disconnect.** "Disconnect Rezdy" asks inline, then sends one `DELETE`.
- **Notices.** Safe success notices (`role=status`) and errors (`role=alert`).
- **Test connection** (administrators). It runs the 2A route with a bodyless POST. The outcome shows in plain words (e.g. "Connected. Rezdy accepted the saved API key."), with a "Needs attention" or "Passed" badge and a collapsible "Security checks: N of M passed".
- **Platform manager view.** Safe state only: no Add/Replace/Remove/Disconnect and no test.
- **Nothing server-side named.** No server file, master key or shell step appears; contract and regression both assert this.

**CSS.** The retired `cz-settings-deck*` rules were replaced by `cz-station-settings*`, `cz-api-keys*` and `cz-settings-notice`. Net +3 lines in `admin-station.css`, which was already over the size limit and is not substantively expanded.

### Deterministic validation (local, at `7b6864b`)

- `npm test` → **exit 0**: typecheck, all PHP tests, build, JS **24/24**, docs check (46 Markdown files, 19 Code Maps).
- `contract:settings-station`: 56 checks, rewritten for the registry and retirement.
- `regression:services-settings`: **47** checks. It replaces `regression:settings-home` and mounts the real `ServiceSettingsLane` through the real registry. It covers structure, launcher intents, the write-only key flow, remove/disconnect confirmation, no secure storage, the platform-manager view, Test connection, and the Service-fields flows.
- `contract:station-tabset`: 66.

### Docs updated

- Settings Code Map: placement, Frontend section, test names.
- Station Manager map: new registry and the boot lock.
- Station Tab Set map: consumers.
- Admin Station Navigation: Settings has no destination.
- Service Catalogue and List System: the launchers moved.
- `ai-index` and the Code Map index: now "Settings and Security".
- Roadmap: 2B/2C status lines.
- Settings, Service and backend `CLAUDE.md` files.

### Flags for Reviewer

1. **Retirement is in this candidate.** The standalone Settings navigation is gone once this deploys. Parity is complete (Connections → API Keys, Service Meta → General → Service fields, Security check → Test connection), but it is still a visible change for anyone who used the old Settings entry.
2. **Bin is kept** in the Services deck, although the Owner tree lists only Details / Connections / Settings. The tree defines the Settings hierarchy, and Bin is accepted Phase 6 function.
3. **The Tools Rezdy slot is a placeholder** that states the importer is not available. Reviewer may prefer to omit Tools until Phase 3, since the pattern allows omitting a section.
4. **Test connection is the 2A validation route.** Each click makes one Rezdy staging call (staging only, administrators only). The route name is still `broker-validation`.

### Next, after approval

Builder pushes exactly `7b6864b` to `staging`, verifies CI, `deploy-staging` and the live bundle, then sets this file to `BLOCKED — OWNER UI REVIEW REQUIRED` with the SHA and `https://staging2.qldscubadive.com.au/station/` (Services → Settings → Security → API Keys), and stops. Phase 2D is not started.


## Reviewer decision — Phase 2B/2C pre-staging review

Verdict: Proceed with safeguards

Reviewed exact candidate `7b6864b3b7ca6c4584662cf6efc22b2896365f07` against staged Phase 2A `68a95c27d1a52af13f10bcd946abea4139383cc3`.

### Accepted

- Settings is now presented inside the Services Station rather than as a separate Station.
- The reusable Settings pattern is `General → Tools → Security` and preserves owner/persistence boundaries through Station Manager.
- `Services → Connections` is untouched as Service-domain relationships.
- The standalone Settings navigation/deck is retired with replacement parity.
- `Security → API Keys` uses the existing Settings/Security backend and `qsd/v1`; no second API/store/broker is introduced.
- Provider credentials remain write-only: Add/Replace uses a password input, saved values are never rendered back, Remove/Disconnect are confirmed, and provider keys are never moved into server/config files.
- The master-key/operator boundary remains hidden from normal admin operation.
- Test connection remains the bounded Phase 2A server-side validation route.
- Source files added/expanded in this candidate remain within normal authored-file limits; the existing oversized CSS file is not substantively expanded.
- Local deterministic validation reported by Builder is consistent with the reviewed source/contract changes.

### Required correction before staging — View Only must really be view only

The approved Phase 2C roadmap says an ordinary `manage_qsd` user may see **safe state only**.

In `SecurityApiKeysPanel.tsx`, provider non-secret fields (currently Rezdy Environment) remain editable even when `tools.canManageSecrets === false`. The regression explicitly expects a platform manager to change Environment.

That contradicts the approved Security UI contract and the visible label `Your access: View only`.

Builder must make the API Keys provider configuration read-only when the user cannot manage secrets:

- disable/non-editable provider configuration controls when `canManageSecrets` is false;
- do not send PUTs from the View Only state;
- keep safe state visible;
- keep Add/Replace/Remove/Disconnect/Test connection unavailable as already implemented;
- update the Services Settings regression/contract to assert that View Only performs no mutation;
- do not change backend capability rules in this correction — this is the Security/API Keys presentation boundary only.

After the correction:
1. run `npm test` and `npm run docs:check`;
2. push the corrected exact topic SHA;
3. return to Reviewer before staging deployment.

Do not deploy `7b6864b` as-is. Phase 2D remains blocked.


## Builder correction handoff — View Only is truly view-only (2026-10-06)

- **Candidate SHA:** `81f749d7cf35b232369cf6af9db94fe6a312f2be` on `docs/settings-security-roadmap`. Remote verified. Parent: `7b6864b`.
- **Correction diff:** 3 files, +14 / −6. Presentation and tests only; backend capability rules are unchanged.
- **`staging`:** still `68a95c2`. Not deployed, per the Reviewer's instruction.

**Change in `SecurityApiKeysPanel.tsx`**
- `const viewOnly = !tools.canManageSecrets`.
- Provider configuration controls (the Rezdy Environment select, and any text config field) are rendered with `disabled={busy || viewOnly}`; text inputs are also `readOnly`. Safe state stays visible.
- `setConfig` returns at once when `viewOnly`, so the View Only state can send no PUT even if a disabled control fires an event.
- Add/Replace/Remove/Disconnect/Test connection were already unavailable in View Only, and remain so.

**Tests**
- `regression:services-settings` (now 48 checks), section 9 (platform manager):
  - asserts the environment shows its value but is disabled;
  - then changes the select and clicks every button in the API Keys panel, and asserts **zero** mutations.
  - The old check, which expected a platform manager to change Environment, is removed.
- `contract:settings-station` (57 checks): new source check for the `viewOnly` guard and that both config controls are disabled with `viewOnly`.
- `npm test` → **exit 0**: JS 24/24, all PHP tests, build, docs check (46 Markdown files, 19 Code Maps). `npm run docs:check` → passed.

**Next, after approval:** Builder pushes exactly `81f749d` to `staging`, verifies CI, `deploy-staging` and the live bundle, then sets this file to `BLOCKED — OWNER UI REVIEW REQUIRED` with the SHA and staging2 URL, and stops. Phase 2D is not started.


## Reviewer decision — corrected 2B/2C candidate

Verdict: Proceed

Reviewed exact correction candidate `81f749d7cf35b232369cf6af9db94fe6a312f2be` against `7b6864b3b7ca6c4584662cf6efc22b2896365f07`.

The requested View Only correction is present and bounded:

- provider configuration controls are disabled when `canManageSecrets` is false;
- `setConfig` returns before mutation in View Only state;
- Add/Replace/Remove/Disconnect/Test connection remain unavailable;
- safe provider state remains visible;
- no backend capability rule changed;
- regression now asserts zero mutations from the View Only state;
- contract pins the read-only guard.

The correction touches only the intended presentation/tests and does not widen Phase 2C.

### Next Builder action — deploy exact candidate

Builder is authorised to promote **exact SHA `81f749d7cf35b232369cf6af9db94fe6a312f2be`** to `staging` using the existing guarded workflow.

After deployment:

1. verify CI/test and `deploy-staging` succeeded for that exact SHA;
2. verify the deployed staging2 bundle contains the 2B/2C Settings/Security UI;
3. set this work file to `BLOCKED — OWNER UI REVIEW REQUIRED`;
4. provide the Owner the staging2 URL and path: `/station/` → Services → Settings → Security → API Keys;
5. stop.

Do not begin Phase 2D. Do not ask the Owner for backend/server work. The next interaction after deployment is Owner visual/interaction review of the actual QSD UI.


## Builder staging2 deployment — Phase 2B/2C `81f749d`; Owner UI gate opened (2026-10-06)

**Status set to `BLOCKED — OWNER UI REVIEW REQUIRED`.** The Builder has stopped. A normal "run the cycle" does not cross this gate; only an explicit Owner acceptance or correction of the UI does.

**Promotion**
- Fast-forward check passed: `origin/staging` (`68a95c2`) is an ancestor of `81f749d7cf35b232369cf6af9db94fe6a312f2be`.
- Pushed `68a95c2..81f749d` to `staging`; `git ls-remote` shows `staging` = `81f749d7cf35b232369cf6af9db94fe6a312f2be`.

**CI and deploy:** Actions run `37448258470` ("Test and deploy"), head `81f749d`: `test` completed success, `deploy-staging` completed success. The workflow, paths and scope are unchanged, and production was not touched.

**Live checks (unauthenticated, from Builder)**
- `https://staging2.qldscubadive.com.au/station/` → 200.
- The deployed `qsd-platform/dist/js/admin-station.js` contains the 2B/2C UI: `settings.api-keys`, "Secure storage:", "Test connection", "View only. Only a site administrator…", and "Rezdy importer" / "Not available yet". The retired `'settings-deck'` kit is absent.
- `POST qsd/v1/admin/settings/security/broker-validation` anonymously → 401, so the route is registered and protected.

**Owner review surface**
- URL: `https://staging2.qldscubadive.com.au/station/` → **Services** → **Settings** → **Security** → **API Keys**.
- Candidate SHA: `81f749d7cf35b232369cf6af9db94fe6a312f2be`.
- Also visible there: Settings → **General** (Create Service / Create Category, Service fields) and **Tools** (Rezdy importer, not available yet). The old standalone Settings header entry is gone.

**What the Owner can do at the gate (UI only)**
- Review the look and interaction, then accept or give corrections.
- If "Secure storage" reads **Ready**, the Owner may set Rezdy Environment to *Staging (sandbox)*, **Add** the Rezdy staging API key, **Save**, then **Test connection**. Its safe result supplies the deferred 2A runtime evidence.
- If "Secure storage" reads **Not set up**, that is the platform-operator master-key provisioning item, not an Owner task. The Builder will record `BLOCKED — INFRASTRUCTURE KEY PROVISIONING REQUIRED` when told.

Not started: Phase 2D, Phase 3. `main` is unchanged (`da93493`).


## Owner direction — single Builder workload (2026-10-06)

The Owner accepts the Security/API Keys placement and restores the intended credential flow: an authorised Admin Station user manages provider API keys entirely through Services → Settings → Security → API Keys. QSD owns the internal secure-storage and broker machinery required for that flow. Normal admin use must not require server-file edits, shell access, hosting setup, or direct database work.

Builder must execute this as one uninterrupted workload, using separate local commits for each phase and no intermediate review stops:

- Phase A: correct roadmap, credential-broker contract and affected Code Map authority to this Owner-approved flow.
- Phase B: correct the existing Security backend so first provider-key save works through Admin Station without a separate operator setup prerequisite. Reuse the existing credential store/cipher/broker; do not create a parallel system.
- Phase C: complete/verify short-lived scoped broker access so Tools receive only broker authority, never the long-lived provider credential. Preserve server-derived user/caller identity, provider/operation binding, expiry, atomic single-use and replay protection.
- Phase D: correct rotation/re-seal so QSD owns the normal operation while preserving fail-closed, all-or-nothing guarantees and never exposing internal key material.
- Phase E: remove operator-setup UI copy/state, preserve View Only as zero-mutation, update tests/contracts/maps, then run npm test and npm run docs:check from wp-content/plugins/qsd-platform/.

Create one local commit per phase. Do not push, deploy or merge any implementation phase individually. Continue through all phases unless a genuine QSD platform-architecture conflict, destructive migration, new Platform ID/entity decision, production/deployment widening, or another repository hard stop gate is discovered; in that case stop and report the conflict instead of improvising.

After all phases and validation are complete, stop before the first implementation push. Report the ordered local commit SHAs, changed scope, validation results, deviations and working-tree status, then wait for explicit Owner push approval.


## Owner confirmation — continue A–E (2026-10-06)

The Owner confirms that the direction recorded at coordination commit `390fb5062deeabef3f9db3feb0540382d0e77241` is authentic and should be followed. Continue Phases A–E as the single uninterrupted Builder workload.

The proposed bootstrap design is approved **with safeguards**:

- on first provider-secret save, QSD may generate a random internal data-encryption key (DEK) and store only a wrapped/encrypted form of that DEK;
- derive the wrapping authority from existing WordPress secret-key material using a standard KDF/context separation, rather than storing a new plaintext platform root key in the database;
- continue using the existing QSD credential cipher/broker boundaries and XChaCha20-Poly1305 for secret envelopes; do not create a parallel credential system;
- bind the wrapped DEK to a QSD-specific context/version so it cannot be confused with other WordPress secret uses;
- never expose the DEK, derived wrapping key, provider secret, request-key hash or equivalent secret material through UI, REST, logs or audit output;
- if WordPress secret keys change and the wrapped DEK can no longer be opened, fail closed, preserve the encrypted provider records, report safe unavailable state, and allow an administrator to replace/re-enter provider credentials through Security → API Keys. Do not silently delete credentials;
- do not invent a broader browser/server-secret-management system. Any rotation control must remain inside the existing QSD Security authority, be administrator-only, use `qsd/v1`, expose no raw key material, and preserve all-or-nothing re-seal guarantees;
- Tools continue to receive only short-lived scoped broker authority, never the long-lived provider credential.

The Builder's stop for confirmation was correct because this was a security-architecture choice. That choice is now resolved. Resume Phase A from the current uncommitted documentation edits, then continue through B–E without intermediate review stops. Keep one local commit per phase and stop before the first implementation push as already instructed.
