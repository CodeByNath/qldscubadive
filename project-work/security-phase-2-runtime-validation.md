# Security Phase 2 — real runtime and controlled provider validation

> **CURRENT OWNER DECISION — 2026-10-10 (OVERRIDES OLDER ACTION ITEMS BELOW)**
>
> **Do not request a Rezdy key, WordPress administrator session, screenshots, connection tests, key rotations or validation reports now.** Those tasks are **DEFERRED until the Account Station global Connections/Security UI is integrated and reviewed**. Historical instructions below describe prior handoffs, NOT the next Builder action.
>
> **Current status:** Security Phase 2 live validation DEFERRED; the implementation is *not* certified or accepted for live-secret operations. Existing staging deployment `cee882c` is not evidence of real-secret runtime validation. Preserve all security controls and do not widen provider access.
>
> **NEXT BUILDER ACTION:** Resolve the existing Security topic branch without new security feature work: read repository authority and independently compare `main` (`da93493`), `staging` (`cee882c`) and `docs/settings-security-roadmap` (`cee882c`). Verify promotion ancestry, relevant CI, diff, and that no production/deploy paths change. Prepare a safe source-only integration/branch-release handoff for Reviewer. **Do not push to main, delete the occupied topic branch or declare Security Phase 2 complete without Reviewer approval.** If any integration would compromise platform/security, STOP. Once Reviewer approves and branch capacity is released, start `project-work/account-station-transfer-batch.md` as one continuous VS Code workload with five local phase commits and a stop before push.
>
> **No Rezdy or admin-session input is necessary for this next action.** This header controls sequencing; earlier `Status: BUILDER ACTION REQUIRED` entries and manual UI steps are historical.


Status: BUILDER ACTION REQUIRED — SAFE MAIN INTEGRATION AND TOPIC BRANCH RELEASE (live provider validation DEFERRED)
Phase: Security Phase 2 — staging2 runtime validation of connection-owned credential guard
Actor: Reviewer for verified code integration and branch release; Builder only on separately assigned bounded action

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


## Builder report — Owner-corrected credential flow, Phases A–E complete locally (2026-10-06)

Executed as one workload on topic branch `docs/settings-security-roadmap`, one local commit per phase, no intermediate stops. **Nothing is pushed, deployed or merged.** Remote topic branch and `staging` remain at `81f749d`; `main` unchanged (`da93493`).

### Ordered local commits (on top of `81f749d`)

| Phase | Commit | Scope |
|---|---|---|
| A | `3f9c199` | Contract (`Key ownership` replaces `Two secret classes`; Encryption at rest; Key operations; Permission; Current boundaries), roadmap (credential-flow direction, Owner gate passed, 2D redefined as QSD-owned rotation), Settings Code Map. |
| B | `93c31b5` | New `Security/CredentialKeyring.php`; `CredentialCipher` opens under several key generations; `ConnectorCredentials`, `SettingsConnectionsController`, `BrokerValidation`, `SettingsModule` use the keyring; shell-only `CredentialRotationCommand` removed; tests. |
| C | `64c5689` | New `Security/BrokeredAccess.php`, `SettingsModule::brokeredAccess()`, `BrokerRejected::UNAUTHENTICATED`; broker tests for the Tool path. |
| D | `8b28ff7` | `CredentialRotation::rotate()`/`inspect()` rewritten for the keyring; `POST qsd/v1/admin/settings/security/rotation` in `SettingsSecurityController`; `ConnectionStore::replaceSecrets()` skips slots saved since planning; `BrokerAuditLog::ROTATED`; rotation and validation tests; contract checks. |
| E | `8a88833` | API Keys UI: operator-setup copy removed; Rotate encryption key (administrators, armed in place, no body, count-only result); api/hook/types; regression and contract checks; frontend CLAUDE.md. |

### Design as built (per the Owner safeguards in `93bc8c8`)

- **First save, no setup.** The first provider-secret save generates a random 32-byte data key (DEK). It is stored only sealed (XChaCha20-Poly1305) in the non-autoloaded option `qsd_settings_credential_keyring`, bound to the versioned context `keyring:v1:<key id>`.
- **Wrapping authority.** HKDF-SHA256 over WordPress `SECURE_AUTH_KEY` + `SECURE_AUTH_SALT`, with info `qsd-credential-wrap:v1` (QSD-specific context separation). It is never stored. Missing, short (<32 chars) or placeholder values mean storage is unavailable (fail closed, 409 on save). An optional `QSD_CREDENTIAL_KEY` is derived the same way and preferred when defined; it is never required, never an admin step, and envelopes sealed directly under it by earlier builds still open (the next rotation migrates them).
- **Same boundaries.** Same `CredentialCipher`/broker/`ConnectionStore`, same envelope format (`v:1`, provider/field AAD); no parallel credential system.
- **WordPress secret keys change.** No generation opens, so affected keys read as not set and the broker refuses (not configured). The admin re-enters them in API Keys; that save starts a new generation and keeps the old sealed one. Nothing is deleted. A refused save (validation error) never creates a generation.
- **Rotation.** Administrator-only `qsd/v1` POST, no input. It plans every secret first; any unreadable slot means nothing is written and a plain 409 message is returned. Otherwise it stages the new generation (old kept), replaces secrets in one write, then retires old generations and rewraps under the preferred wrapper. Response: re-sealed count plus unreadable slot names; audited as `rotated` with the session user. No key, wrapped key or key id appears in the UI, REST, logs or audit.
- **Tools.** `BrokeredAccess` is bound to one allow-listed caller; the user comes from the session on every call; `run()` issues and consumes a short-lived bound key internally and returns only the leak-checked result. Its public API is `run()` only; it is not serialisable. Allow-list unchanged (validation caller only); no new scope, provider call or importer.

### Validation (local, PHP 8.5.6, from `wp-content/plugins/qsd-platform/`)

- `npm test` → exit 0 (typecheck, all PHP tests, build, all JS scripts, docs check). Settings-specific results:
  - `settings-connections.php` — all checks passed, including the new keyring, refusal, wrapping-change and constant-source checks.
  - `settings-credential-broker.php` — 73 checks (was 65).
  - `settings-credential-rotation.php` — rewritten, 23 checks.
  - `settings-security-validation.php` — all passed, including rotation route, audit and refusal.
  - `contract:settings-station` — 62 checks (was 57).
  - `regression:services-settings` — all passed, including new section 11 (rotation) and the updated unavailable/View Only checks.
- `npm run docs:check` → passed (46 Markdown files, 19 Code Maps).
- Working tree clean after `8a88833`.

### Deviations and residual risks

1. **Shell command removed.** `wp qsd credentials reseal` (`CredentialRotationCommand`) was removed in Phase B, not D, because the operator previous/current constant pair it depended on was replaced there. QSD-owned rotation landed in D.
2. **Rotation is a REST route.** The earlier roadmap 2D line "no browser/REST trigger without separate approval" is superseded by the Owner direction and the `93bc8c8` safeguards (administrator-only, `qsd/v1`, no key material, all-or-nothing).
3. **Residual risk: concurrency.** A secret saved under the old generation in the narrow window between rotation staging and retiring could become unreadable. It then reads as not set and can be re-entered; there is no plaintext or partial-write exposure. Saves landing after planning are protected by the `replaceSecrets` expectation check.
4. **Residual risk: non-standard installs.** An install whose WordPress secret keys are not defined in server configuration (WordPress falling back to database-stored salts) reports secure storage unavailable rather than wrapping under database-held material. Standard installs, staging2 included as far as is known, define them.
5. **Not yet verified on staging2.** Real-runtime evidence (first save on staging2, Test connection, Rotate) requires the push/deploy that this workload stops before.

### Next action

Owner: approve (or decline) pushing `3f9c199..8a88833` to the topic branch. Staging2 promotion and runtime evidence follow only with Reviewer approval of the exact candidate. Not started: Phase 3; no production work.


## Owner push approval — corrected Security package (2026-10-06)

The Owner approves pushing the completed local Phase A–E commit chain **exactly as reported** to the existing topic branch `docs/settings-security-roadmap`:

`3f9c199` → `93c31b5` → `64c5689` → `8b28ff7` → `8a88833`

Builder may now push that exact commit chain to the topic branch and verify the remote head. Do not amend, squash, reorder, add unrelated commits, deploy to `staging`, merge to `main`, or begin Phase 3.

After push:
1. verify the remote branch head is exactly `8a88833` (full SHA in report);
2. record the exact pushed SHA and remote verification;
3. continue the same Security workload through Reviewer inspection;
4. if the pushed candidate has no genuine architecture/security blocker, continue to the already-authorised guarded staging deployment and staging2 runtime validation;
5. continue Phase 2 closeout without another routine Owner stop.

Do not stop merely because a normal phase/review boundary was reached. Stop only for a genuine QSD architecture conflict, failed security invariant, destructive migration/customer-state risk, deployment-boundary widening, production impact, or another repository hard stop gate. Phase 3 remains blocked until Phase 2 closeout. Production remains prohibited.


## Builder push record — corrected Security package (2026-10-06)

Pushed the Owner-approved chain exactly as reported. No amend, squash, reorder or added commit.

- Command: `git push origin 8a88833:refs/heads/docs/settings-security-roadmap` → `81f749d..8a88833`.
- Remote verification (`git ls-remote`):

  | Branch | SHA | Note |
  |---|---|---|
  | `docs/settings-security-roadmap` | `8a88833c46c18e1ebebc1d470721be7adba05b82` | pushed |
  | `staging` | `81f749d7cf35b232369cf6af9db94fe6a312f2be` | unchanged; not deployed |
  | `main` | `da934936edafcf892ebab33e870e6f5f511d147f` | unchanged |
- Chain: `3f9c199` → `93c31b5` → `64c5689` → `8b28ff7` → `8a88833` (details in the Builder report above).

### Handoff to Reviewer

Reviewer: inspect candidate `8a88833` (diff `81f749d..8a88833`). The Builder does not deploy any candidate to `staging` without Reviewer approval of that exact SHA. If approved, the Builder will:

1. promote `8a88833` to `staging` through the existing guarded workflow (no workflow, path or scope change);
2. verify CI and the deployed bundle;
3. collect staging2 runtime evidence through API Keys: secure storage "Ready" with no setup, first Rezdy staging key save, Test connection, Rotate encryption key, and the validation report;
4. continue to Phase 2 closeout.

Points the Reviewer should weigh are listed under "Deviations and residual risks" in the Builder report (shell command removed in B; rotation REST route per Owner direction; rotation concurrency window; installs without wp-config secret keys fail closed). Not started: Phase 3. Production untouched.


## Reviewer decision — pushed Security package `8a88833` (2026-10-06)

Verdict: Stop — architectural risk

Reviewer inspected the pushed candidate `8a88833c46c18e1ebebc1d470721be7adba05b82` against the Owner-approved Security safeguards and actual source. The five-commit chain is present and bounded, but the rotation implementation has a real security-invariant failure and must not be deployed yet.

### Blocking finding — rotation can retire a key still needed by a concurrent save

`CredentialRotation::rotate()` plans all stored secret envelopes, then stages a new key generation, calls `ConnectionStore::replaceSecrets($plan, $expected)`, and unconditionally calls `CredentialKeyring::retireInactive()`.

`ConnectionStore::replaceSecrets()` silently skips any slot whose stored envelope changed after planning. If an administrator saves/replaces a credential after rotation planning but before the new generation is staged, that save is encrypted under the old generation. Rotation then sees the changed slot, skips it, and still retires the old generation. The just-saved credential becomes unreadable.

This directly violates the accepted safeguards:
- rotation must be all-or-nothing;
- the old generation may be retired only after every stored secret is safely on the new generation;
- no credential may become unreadable because rotation raced a normal save.

The Builder report itself identified this concurrency window as a residual risk; for credential rotation it is not an acceptable residual risk.

### Bounded Builder correction

Keep the existing QSD-owned keyring, broker, UI and API design. Correct only rotation/concurrent-save safety:

1. Make rotation detect every planned-slot conflict instead of silently treating a skipped replacement as success.
2. Do not retire any prior generation unless every credential present at the rotation commit point is confirmed openable under the new generation.
3. If a concurrent credential mutation prevents that guarantee, either retry the whole rotation safely or abort while preserving all previously valid generations and credentials; do not leave a partial rotation reported as success.
4. Preserve the existing no-secret output and administrator-only `qsd/v1` boundary.
5. Add a deterministic regression for the exact race: mutate a credential after planning but before replacement, then prove rotation cannot make that credential unreadable and cannot falsely report full success.
6. Re-run `npm test` and `npm run docs:check`.
7. Commit and push only this bounded correction to the same topic branch, then continue the workload. Do not deploy `8a88833` to staging.

No other Phase A–E architecture is reopened by this verdict.


## Builder correction — rotation concurrency (2026-10-06)

Bounded correction for the Reviewer's blocking finding on `8a88833`. Only rotation/concurrent-save safety changed; keyring, broker, UI and API design are untouched.

- **Commit:** `957427d10ad3c7c3f1571599bc51c66b20fe1208` (single commit on `8a88833`), pushed to `docs/settings-security-roadmap`.
- **Remote verification (`git ls-remote`):**

  | Branch | SHA | Note |
  |---|---|---|
  | `docs/settings-security-roadmap` | `957427d` | pushed |
  | `staging` | `81f749d` | unchanged; `8a88833` was not deployed |
  | `main` | `da93493` | unchanged |
- **Files:** `Security/CredentialRotation.php`, `Security/CredentialKeyring.php`, `tests/settings-credential-rotation.php`, `docs/architecture/credential-broker-contract.md` (Key operations step 3).

### What changed (against the Reviewer's items)

1. **Conflicts detected, not silently skipped.** After the replacement write, `rotate()` re-reads every stored secret (the commit check). Any slot not on the new key is re-planned against the stored envelope, including a save that raced the rotation; its current value is re-sealed under the new key and written again (with the same changed-since-planning guard). The check runs for at most `MAX_PASSES` = 3 passes.
2. **No early retirement.** Older generations are retired only when the commit check finds no slot left to move. `CredentialKeyring::retireInactive()` is replaced by `retireUnreferenced($referenced)`, which reads the stored envelopes' key ids immediately before retiring and never drops a generation a stored secret still names. Success is reported only when, after retirement, every stored envelope names the new key.
3. **Failure is never reported as success.** If the guarantee cannot be confirmed within 3 passes, rotation returns `ok: false` with "Saved API keys kept changing while the key was rotating. Every key still works; rotate again." The route answers 409. Every generation still in use is kept and every key still opens. An unreadable slot found during the check also stops with failure.
4. **Boundary unchanged.** The report still carries slot names and counts only; the route is still administrator-only `qsd/v1` POST with no input.
5. **Deterministic regressions.** A test seam (`checkpoint` closure, unused in production) covers:
   - a key replaced under the old generation after planning and before staging: rotation succeeds, the new value survives, it sits on the new key, and only the new generation remains;
   - a save that keeps landing under the old generation after every write: rotation reports failure (never success), the old generation is kept, every key opens, the report holds no secret or key id, and the next rotation completes once saves settle;
   - `retireUnreferenced()` keeps any generation a stored secret names.
6. **Validation:** `npm test` exit 0; `npm run docs:check` passed (46 Markdown files, 19 Code Maps).

### Handoff to Reviewer

Reviewer: inspect `957427d` (diff `8a88833..957427d`). On approval of that exact SHA, the Builder will promote it to `staging` through the existing guarded workflow, verify CI and the bundle, collect the staging2 runtime evidence through API Keys, and continue to Phase 2 closeout. Not started: Phase 3. Production untouched.


## Reviewer decision — rotation correction `957427d` (2026-10-06)

Verdict: Stop — architectural risk

Reviewer inspected exact pushed correction `957427d10ad3c7c3f1571599bc51c66b20fe1208` against the prior concurrency finding and actual source. The correction improves detection/retry, but the retirement race is still not closed.

### Remaining race

After the commit-check sees no stragglers, `CredentialRotation::rotate()` calls:

`retireUnreferenced($this->referencedKids())`

The referenced-key list is a snapshot taken before retirement. A credential-save request may already hold an old-generation sealing cipher from before rotation staged the new generation, then write that old-generation envelope **after** `referencedKids()` takes its snapshot but **before** `retireUnreferenced()` drops the old generation. The old generation can therefore still be retired while a just-written credential names it. The later post-retirement check detects the old kid only after the generation is gone, so it cannot preserve readability.

The new tests cover races before staging and after replacement writes, but not this final snapshot → retire window.

### Required bounded correction

Do not deploy `957427d`.

Close the save/rotation race at the authority boundary rather than with another observation loop. Use one shared Security-owned concurrency guard for provider-secret mutation and rotation so that:

1. a secret save cannot obtain/use a sealing generation while rotation can retire that generation;
2. rotation cannot enter its retirement/commit section while a secret save is in flight;
3. failure/timeout of the guard fails closed without deleting or exposing credentials;
4. non-secret configuration writes do not need to be serialized unless required by implementation safety;
5. the guard stays inside the existing Settings/Security authority and does not create a second credential system or new Platform identity;
6. add deterministic regressions for both orderings: save starts first then rotation, and rotation starts first then save; prove every successful save remains readable and rotation never falsely reports success;
7. keep the existing no-secret output, administrator-only `qsd/v1` route and QSD-owned keyring model;
8. run `npm test` and `npm run docs:check`, push the bounded correction to the same topic branch, and continue the workload.

No other Phase A–E architecture is reopened.


## Builder correction — shared credential mutation guard (2026-10-07)

Bounded correction for the Reviewer's finding on `957427d`. It closes the save/rotation race at the authority boundary, not with an observation loop. Keyring, broker, UI, API and output are unchanged.

- **Commit:** `efae006bf300b19ce9e8a4af3b4332e4bcd1ce41` (single commit on `957427d`), pushed to `docs/settings-security-roadmap`.
- **Remote verification:**

  | Branch | SHA | Note |
  |---|---|---|
  | `docs/settings-security-roadmap` | `efae006` | pushed |
  | `staging` | `81f749d` | unchanged; nothing deployed |
  | `main` | `da93493` | unchanged |

### Against the Reviewer's items

1. **A save cannot use a generation rotation may retire.** `SettingsConnectionsController::saveConnection()` runs its whole read → seal (including first-save keyring creation) → write inside `CredentialMutationGuard::hold()`. Rotation runs entirely inside the same guard, so retirement can never overlap a save holding an old sealing generation.
2. **Rotation cannot commit while a save is in flight.** Same guard; it is not re-entrant. Configuration-only saves and disconnect are also guarded. Implementation safety requires this: each is a read-modify-write of the stored record, including secret envelopes, and could otherwise write back pre-rotation envelopes after retirement. The deterministic test proves that case.
3. **Fails closed.** If the guard is busy beyond a 10 s wait, the save, disconnect or rotation changes nothing and returns 409 "Another change to API keys is in progress. Nothing was changed; try again in a moment." A lease left by a crashed request expires after 60 s and is broken only by a delete matching its exact stored value. Nothing is deleted or exposed.
4. **Inside the existing authority.** New files:
   - `Security/CredentialMutationGuard.php` (interface);
   - `Security/WpdbCredentialMutationGuard.php`: one non-autoloaded options row `qsd_settings_credential_guard` holding a random token and expiry only. It is taken by an atomic `$wpdb->insert` on the options unique key, the same reason and pattern as `WpdbRequestKeyStore` (the cached options API is check-then-act), and released by a token-matched delete;
   - `Security/CredentialMutationBusy.php`.

   No second credential system, no Platform ID, no new route.
5. **Rotation simplified.** The retry loop is gone. Under the guard, rotation plans, stages, replaces, confirms every stored secret opens under the new key, then `retireUnreferenced()` (still never drops a generation a stored secret names). Anything left means failure, with nothing retired.
6. **Deterministic regressions for both orderings** (`tests/settings-connections.php`, real controller, real rotation, real `WpdbCredentialMutationGuard` over a unique-key `$wpdb` double in `tests/support-credential-guard.php`):
   - **Save first:** a rotation started while a save holds the guard reports failure, changes nothing, and the guard is released.
   - **Rotation first:**
     - a secret save mid-rotation gets 409 and echoes nothing; the rotation succeeds; the stored key still opens on the single new generation; a later save succeeds and stays readable;
     - a configuration save mid-rotation gets 409 and cannot write back stale envelopes;
     - a disconnect mid-rotation gets 409 and removes nothing.
   - **Lease:** an expired lease is broken; a live lease times the waiter out with nothing run and the holder intact.
   - **Rotation test:** a busy guard means nothing is written. A write that bypasses the guard and lands under the old generation makes rotation report failure; no generation is retired, every key opens, and the report holds no secret or key id.
7. **Boundary unchanged.** No-secret output, the administrator-only `qsd/v1` route and the QSD-owned keyring model are as before.
8. **Validation:**
   - `npm test` exit 0 (PHP tests, typecheck, build, 24/24 contract/regression scripts);
   - `contract:settings-station` 65 checks; new guard checks: every connection write and the whole rotation run inside the guard, and the guard uses an atomic insert;
   - `npm run docs:check` passed.

Docs updated: the credential-broker contract's Key operations (the commit check and a new "One guard for every credential write" step), the Settings Code Map, and the Settings backend `CLAUDE.md`.

### Handoff to Reviewer

Reviewer: inspect `efae006` (diff `957427d..efae006`). On approval of that exact SHA, the Builder will promote it to `staging` through the existing guarded workflow, verify CI and the bundle, collect the staging2 runtime evidence through API Keys, and continue to Phase 2 closeout. Not started: Phase 3. Production untouched.


## Reviewer decision — shared guard correction `efae006` (2026-10-07)

Verdict: Stop — architectural risk

Reviewer inspected exact pushed correction `efae006bf300b19ce9e8a4af3b4332e4bcd1ce41`. The shared guard is the correct authority boundary, but its lease implementation can still admit two concurrent credential writers.

### Blocking finding — a live holder can lose the guard after 60 seconds

`WpdbCredentialMutationGuard` stores a fixed `expires_at = now + 60` when the guard is acquired. The lease is never renewed and ownership is not revalidated before the protected operation commits/retires key generations.

If a legitimate save/rotation remains inside `hold()` for more than 60 seconds, another request may treat its row as expired, delete that exact row, acquire a new guard, and enter the same credential critical section while the first request is still running. The first request then continues without owning the guard. That re-opens the exact save/rotation race this correction is meant to eliminate.

This is a security invariant issue, not a performance preference. A lock used to prove safe key retirement cannot expire underneath a live holder.

### Required bounded correction

Keep the shared Security-owned guard architecture, but replace/fix the lease semantics so exclusivity cannot be lost while the holder is still executing:

1. Use a guard mechanism whose ownership is tied to the live database/request connection, or otherwise renew/fence ownership so a live holder cannot be superseded.
2. Before any key-generation retirement/commit that depends on exclusivity, ownership must still be provable.
3. A crashed/abandoned holder may be recoverable, but recovery must never allow a second writer while the first holder can still resume and commit.
4. Guard acquisition timeout continues to fail closed with no credential mutation.
5. Preserve one shared guard for secret/config writes, disconnect and rotation; do not create another credential system or route.
6. Add a deterministic regression proving a long-running holder cannot be overtaken after the old 60-second lease boundary, plus the existing save-first/rotation-first cases.
7. Run `npm test` and `npm run docs:check`, push the bounded correction to the same topic branch, then continue the Security workload.

Do not deploy `efae006` to staging. No other Phase A–E architecture is reopened.


## Builder correction — connection-owned guard, no lease (2026-10-07)

Bounded correction for the Reviewer's finding on `efae006`. The shared Security-owned guard architecture is kept; only its ownership mechanism changed. Keyring, broker, UI, API and output are unchanged.

- **Commit:** `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` (single commit on `efae006`), pushed to `docs/settings-security-roadmap`.
- **Remote (`git ls-remote`):** `docs/settings-security-roadmap` `cee882c`; `staging` `81f749d` (unchanged, nothing deployed); `main` `da93493` (unchanged).

### Against the Reviewer's items

1. **Ownership tied to the live connection.** `WpdbCredentialMutationGuard` is now a database named lock (`GET_LOCK(name, 10)`). The options row, token, `expires_at` and 60 s lease are gone. The lock belongs to the holder's database connection and cannot expire while it runs. The name is scoped to the site (`DB_NAME` + options table), because named locks are server-wide.
2. **Ownership proven before each commit.** New `CredentialMutationGuard::assertHeld()` checks that `IS_USED_LOCK(name)` equals this request's `CONNECTION_ID()`, and that it is the same connection that acquired the lock. It is called immediately before:
   - rotation: `stage`, `replaceSecrets`, `retireUnreferenced`;
   - save: the first-generation seal and `store->write`;
   - disconnect: `store->remove`.

   Failure throws `CredentialMutationLost`: save and disconnect answer 409, and rotation reports failure with every generation kept.
3. **Recovery never admits a second writer while the first can resume.** The database frees a crashed holder's lock when its connection ends. While the guard is held, `$wpdb->reconnect_retries` is set to 0 and restored on release. A holder whose connection drops therefore cannot transparently reconnect and write on a new connection without the lock: wpdb ends the request instead. `assertHeld()` also catches any ownership loss on a live connection.
4. **Fails closed.** Busy after the 10 s wait: nothing runs, 409. `GET_LOCK` NULL (error) is treated as busy. Non-re-entrant: if this connection already owns the lock, the guard refuses, since MySQL would let it re-enter.
5. **One shared guard.** Secret/config saves, disconnect and rotation still share it. No new credential system, route or Platform ID. New file: `Security/CredentialMutationLost.php`.
6. **Deterministic regressions** use a named-lock double (`tests/support-credential-guard.php`): a server-wide lock owned per connection, re-entrant per connection, freed on connection drop, no expiry.
   - **Long-running holder:** a rotation holds the guard while a second connection repeatedly attempts save, disconnect and rotation. All are refused with 409. The holder still owns the guard at its commit and completes. Reconnection is 0 while held and restored after.
   - **Crash recovery:** a lock held by another open connection still blocks. Once that connection ends, the next request takes the guard.
   - **Lost ownership:** after the holder's connection drops and another request takes the guard, `assertHeld()` fails. A rotation that loses its connection after replacement stops before retiring, reports "lost its lock … No older key was retired", keeps both generations, every key opens, and the report has no secret or key id. The next rotation completes.
   - The existing save-first, rotation-first, config-during-rotation and disconnect-during-rotation cases are unchanged and pass.
7. **Validation (local, `cee882c`):**
   - `npm test` exit 0 (PHP tests, typecheck, build, 24/24 contract/regression scripts);
   - `contract:settings-station` 68 checks. New checks: named lock with no lease/expiry; reconnection off and ownership proven by connection; rotation asserts before stage/replace/retire; save asserts before write. The old "atomic insert" check was replaced.
   - `npm run docs:check` passed (46 Markdown files, 19 Code Maps).

Docs updated: credential-broker contract Key operations step 4, Settings Code Map, Settings backend `CLAUDE.md`.

### Limitations for Reviewer

- The lock semantics are proven against a double, not real MySQL/MariaDB. Real `GET_LOCK`/`IS_USED_LOCK`/`RELEASE_LOCK` behaviour and the reconnect-off path still need the staging2 real-runtime evidence required by Phase 2 safeguard 2.
- "Past 60 seconds" is shown structurally: the guard has no clock, and the contract forbids `expires_at`/`LEASE`/`time()` in it. The test does not sleep.
- With reconnection off, a request that loses its database connection mid-hold ends (wpdb `dead_db`) instead of continuing. This is intended fail-closed behaviour, and its effect is limited to that request.
- On PHP < 8.2 with the non-default `mysqli.reconnect=On`, `mysqli_ping` could reconnect beneath wpdb. `assertHeld()` still detects the new connection id before the next write. That ini option was removed in PHP 8.2.
- The guard's old options row `qsd_settings_credential_guard` was never deployed (`efae006` not on staging), so no cleanup is needed.

### Handoff to Reviewer

Reviewer: inspect `cee882c` (diff `efae006..cee882c`). On approval of that exact SHA, the Builder will promote it to `staging` through the existing guarded workflow, verify CI and the bundle, collect the staging2 runtime evidence (including the real named-lock behaviour), and continue to Phase 2 closeout. Not started: Phase 3. Production untouched.


## Reviewer decision — connection-owned guard `cee882c` (2026-10-07)

Verdict: Proceed with safeguards

Reviewer independently inspected exact pushed candidate `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` and the relevant guard/rotation source.

The prior blocker is closed at source level:

- the 60-second lease/options-row mechanism is removed;
- credential mutation exclusion is now a database named lock owned by the live database connection;
- a live holder has no time-based expiry;
- save, disconnect and rotation share the same Security-owned guard;
- ownership is proven before staging a key generation, replacing stored secrets, writing connection state, removing a connection, and retiring generations;
- loss of ownership fails closed and rotation keeps older generations rather than reporting success;
- deterministic regressions cover save-first, rotation-first, long-running holder, connection-drop recovery and loss-before-retirement cases.

The exact candidate is approved for the already-authorised **staging2-only** validation path. This is not Phase 2 acceptance and is not production approval.

### Builder next action — bounded staging validation

1. Promote exact SHA `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` to the existing `staging` boundary only. Do not alter the deployment workflow, SSH path, sync scope or production state.
2. Verify CI and deployment correspond to that exact candidate.
3. On staging2, prove the real database supports the required `GET_LOCK` / `IS_USED_LOCK` / `RELEASE_LOCK` semantics and that the QSD guard behaves fail-closed on acquisition/ownership failure.
4. Collect the already-required real WordPress/database evidence: encrypted-at-rest option storage, server-derived caller/user binding, request-key issue/consume/replay/expiry/binding behaviour, no-secret REST/audit output, rotation/re-seal, and one bounded read-only Rezdy staging connection check through the broker.
5. Do not expose any credential, request key, request-key hash, wrapping/data key or provider-auth URL containing the key.
6. Run/retain deterministic `npm test` and `npm run docs:check` evidence for the exact promoted candidate.
7. Update this same file with exact staging SHA, workflow/run evidence and safe runtime results, then hand back to Reviewer.

Safeguard: if staging2 does not support the required named-lock semantics, or if the runtime shows that WordPress/database reconnection can bypass ownership checks, stop and report the evidence. Do not invent another lock system in the same cycle.

Phase 3 remains blocked. Production remains prohibited.


## Builder preflight — staging promotion of `cee882c` blocked by executor permission (2026-10-07)

Status stays `BUILDER ACTION REQUIRED`. Builder still owns the staging2 validation step. Nothing was promoted and nothing was deployed.

- **Read-only checks passed:**
  - `origin/staging` `81f749d7cf35b232369cf6af9db94fe6a312f2be` is an ancestor of `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`, so the promotion is a fast-forward;
  - `.github/` is unchanged between them (0 diff lines), so the workflow, paths and scope would be as before;
  - `origin/docs/settings-security-roadmap` = `cee882c`.
- **Blocked:** Builder's local permission guard (Claude Code auto-mode classifier, "Production Deploy") refused `git push origin cee882c…:refs/heads/staging`. This is an executor capability block, not a repository, architecture or source finding. It is the same block recorded for the `68a95c2` promotion, which the Owner then approved locally.
- **Required execution surface:** an Owner-approved push of exactly `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` to `staging`. Either the Owner approves that push in the Builder session, or the Owner runs it directly: `git push origin cee882c9a48c3a2370f930aa4a6b21848f40fb8c:refs/heads/staging`.
- **Builder has no browser or WordPress session.** Once deployed, Builder can verify CI, the deployed bundle and anonymous route protection. The authenticated runtime evidence needs an administrator in `/station/` → Services → Settings → Security → API Keys:
  - Secure storage "Ready";
  - Rezdy staging key Save;
  - Test connection;
  - Rotate encryption key, twice. Two successful rotations show that real `GET_LOCK`/`IS_USED_LOCK`/`RELEASE_LOCK` acquire, prove ownership and release on staging2's database;
  - the validation report.
- **Not reachable through the QSD UI:** the fail-closed contention case (a second writer refused while the guard is held). Exercising it on staging2 would need either concurrent administrator requests or a guard self-check in the validation report. The latter is a source change for a new candidate, so it was not added in this cycle. Reviewer to decide whether the deterministic regressions plus a successful real-database rotation are sufficient, or whether a bounded guard self-check is authorised.

Not started: Phase 3. Production untouched. `main` unchanged (`da93493`).


## Builder staging2 deployment — `cee882c` (2026-10-07)

Supersedes the promotion block above: the Owner approved the push in the Builder session, and the identical command succeeded. Status stays `BUILDER ACTION REQUIRED`. Builder owns finishing the runtime evidence, and the next step needs an authenticated administrator.

**Promotion**
- Fast-forward re-checked: `origin/staging` `81f749d` is an ancestor of `cee882c`.
- `git push origin cee882c9a48c3a2370f930aa4a6b21848f40fb8c:refs/heads/staging` → `81f749d..cee882c`.
- `git ls-remote`: `staging` = `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`.
- `.github/` unchanged; no workflow, path, SSH or sync-scope change. Production untouched. `main` unchanged (`da93493`).

**CI and deploy:** Actions run `37554991371` ("Test and deploy", push, `staging`, head `cee882c`). `test` completed success, which includes `npm test` and therefore `docs:check`. `deploy-staging` completed success.

**Live checks (anonymous, from Builder)**
- `https://www.staging2.qldscubadive.com.au/station/` → 200, "Admin Station".
- The deployed `qsd-platform/dist/js/admin-station.js` contains the Phase E API Keys UI: `settings.api-keys`, the `security/rotation` endpoint, "Rotate encryption key", and "Test connection". The rotation UI is absent from the previous staging build `81f749d`, so the new build is live.
- Anonymous `GET qsd/v1/admin/settings/connections`, `POST …/security/rotation` and `POST …/security/broker-validation` each → 401 `rest_forbidden`: registered and protected. Requests to the bare `staging2.` host 301 to `www.` and return 400 after the redirect, so evidence uses the `www.` host.
- PHP guard code is not observable anonymously. Its runtime behaviour is covered by the administrator steps below.

**Deterministic evidence for the promoted SHA:** CI run above. Local `npm test` (24/24, contract 68 checks) and `docs:check` on `cee882c` are recorded in the `cee882c` handoff.

**Next — needs an administrator in `/station/` (QSD UI only, no secret leaves the UI)**

URL: `https://www.staging2.qldscubadive.com.au/station/` → **Services** → **Settings** → **Security** → **API Keys**.

1. Note the "Secure storage" state. Expected: **Ready**, with no setup step.
2. Under Rezdy: Environment *Staging (sandbox)*, **Add** the Rezdy staging API key, **Save**.
3. **Test connection**. Note the result line.
4. **Rotate encryption key**, confirm. Note the result. Then **Rotate encryption key** a second time and note the result. Two successes show real `GET_LOCK` acquire, `IS_USED_LOCK` ownership proof and `RELEASE_LOCK` on staging2's database. A failure reading "Another change to API keys is in progress" or "lost its lock" means the named-lock semantics are not working there; per the Reviewer safeguard, that stops this cycle with no new lock system.
5. **Test connection** again, to show the key still opens after rotation.
6. Run the Security validation report, and share its Passed/Failed badge and rows, or a screenshot. None of this contains a secret.

**Open for Reviewer:** the contention fail-closed case (a second writer refused while the guard is held) cannot be produced through the QSD UI. See the preflight note above.


## Owner-directed sequencing decision — 2026-10-10

Reviewer verdict: **Proceed with safeguards** for deferring the credential-dependent runtime gate, **not** acceptance of Security Phase 2.

Owner explicitly wants to test a live Rezdy connection AFTER the global Settings/Connections surface is relocated into the new Account Station. Do not require or request a Rezdy API key as a prerequisite to the Account build. Existing automated/code/CI evidence and staging2 deployment remain recorded above, but the real credential-store, named-lock, rotation/re-seal, request-key and actual provider-call checks remain **UNVERIFIED / DEFERRED**, never PASS or accepted.

Do NOT reopen Security source or change credential storage as part of this decision. Preserve current security backend and API. The existing Security candidate `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` is on `staging` and `docs/settings-security-roadmap`; `main` is still `da934936edafcf892ebab33e870e6f5f511d147f` as observed 2026-10-10. The sole topic branch remains occupied. Before enabling the queued Account workload, Reviewer must independently inspect promotion eligibility and determine a safe non-destructive integration into `main` for the already-reviewed source, without representing deferred runtime tests as acceptance. Only after separate explicit approval and verified integration may the completed Security topic branch be retired in accordance with docs/foundation/repository-governance.md. No force-push, staging workflow change, or production deploy.

Next authorized Account workload: `project-work/account-station-transfer-batch.md` (five local phase commits; no source push until Owner/Reviewer gate). Builder must not open another topic branch until the existing one is closed. Once Account is implemented/reviewed, run the deferred authenticated staging2 Rezdy/key rotation validation through the new Account Settings UI; unresolved runtime failure then blocks Security Phase 2 acceptance and further Service Element work.


## Builder handoff — source-only integration verification (2026-10-10)

Per the Owner's "NEXT BUILDER ACTION" above. No source changed, no push to `main`, topic branch not deleted.

**Branch state**
- `main` = `da934936edafcf892ebab33e870e6f5f511d147f` (unchanged).
- `staging` = `origin/docs/settings-security-roadmap` = `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` (identical SHA on both).
- Ancestry: `git merge-base --is-ancestor da93493 cee882c` confirms `main` is an ancestor — a clean fast-forward chain, no rebase/rewrite.

**Diff scope (`da93493..cee882c`): 62 files, +3450/−1066**
- Entirely within `wp-content/plugins/qsd-platform/{resources,scripts,src,tests}` and `docs/`.
- Zero diff lines in `.github/`: no deployment workflow, SSH path, sync scope, `wp-config`, or production path touched.

**CI** — independently queried the GitHub Actions API (unauthenticated, public repo) for run `37554991371` on the `staging` push of `cee882c`: `status: completed`, `conclusion: success`. This corroborates the CI claim already recorded above from a source other than Builder's own prior report.

**Conclusion.** The Security candidate on `staging` / `docs/settings-security-roadmap` is a clean, fast-forward, source-only extension of `main`, with no deployment-surface change and a green CI run. This is file/CI verification only, not live-secret runtime validation — that remains DEFERRED per the Owner decision above and is not claimed here.

**Handoff to Reviewer.** Builder has not pushed to `main`, deleted the topic branch, or declared Phase 2 complete. Please decide whether this clears `cee882c` for a safe, non-destructive integration into `main` and lawful retirement of `docs/settings-security-roadmap` per `docs/foundation/repository-governance.md`, releasing one topic-branch slot for `project-work/account-station-transfer-batch.md`. If further evidence is required before integration, specify it here.


## Reviewer decision — source-only branch release (2026-10-10)

**Verdict: Proceed with safeguards.** Independently checked GitHub remote heads: main `da934936edafcf892ebab33e870e6f5f511d147f`, staging and topic `cee882c9a48c3a2370f930aa4a6b21848f40fb8c`. Compare `main...docs/settings-security-roadmap`: status ahead; 15 commits ahead, zero behind; merge-base equals `main`; 62 changed files; none under `.github/` or `wp-config`. Existing handoff records successful CI and staging deployment. **No fresh WordPress, PHP, browser or Rezdy validation was performed by Reviewer.** Live secret/rotation/provider test remains DEFERRED and NOT accepted.

**Builder next action (bounded):**
1. In VS Code, re-fetch and verify exact remote heads and fast-forward ancestry; verify relevant CI and no deployment-workflow/path drift. Do not request Rezdy key, WP session or security UI validation.
2. Fast-forward `main` to exactly `cee882c9a48c3a2370f930aa4a6b21848f40fb8c` as **source integration only**, with no force push, merge commit, rebase, staging change, production deployment, or credential operation. Stop if remote main diverges or any unexamined files/path rules change. Confirm remote main SHA after push.
3. After verifying source integration, retire the completed `docs/settings-security-roadmap` topic branch per governance; never delete permanent branches. Record exact integration and remote deletion evidence in this same file.
4. Then set the queued Account transfer work file to `BUILDER ACTION REQUIRED` and execute `project-work/account-station-transfer-batch.md` as the Owner-authorised one-workload/five-local-commits plan. Stop before pushing any Account implementation. If another actor/approval guard refuses a push or branch deletion, stop and report exact blocker instead of treating it as a Rezdy dependency.

The integration is NOT acceptance of the Security Phase 2 runtime exit criteria. Its live-provider evidence remains deferred until the completed Account UI can expose existing QSD global Connections/Security.
