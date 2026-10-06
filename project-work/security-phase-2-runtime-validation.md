# Security Phase 2 — real runtime and controlled provider validation

Status: BUILDER ACTION REQUIRED
Phase: Security Phase 2 — API / storage / rotation validation
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
