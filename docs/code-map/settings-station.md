# Settings and Security

Settings is a reusable tab pattern inside a Station. It is not a separate Station identity or domain record.

The current standalone Settings navigation/deck on `main` is an accepted foundation implementation but is transitional presentation. The approved target presentation is inside the Services Station:

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

Other Stations may surface `General`, `Tools`, and/or `Security` where relevant. Shared General/Security data is owned once by the platform authority; presentation in another Station does not duplicate persistence or transfer authority.

## Ownership boundaries

- `Services Station → Connections` remains Service-domain relationships.
- `Settings → General` may surface global/platform settings relevant to the hosting Station plus Station-owned settings where appropriate.
- `Settings → Tools` contains operational tools/importers. Rezdy importer belongs here.
- `Settings → Security` owns the UI for API keys and credential/security controls.
- Tools consume Security-governed authority. They never own/read long-lived provider credentials.
- The database remains storage implementation only. Browser/domain consumers go through `qsd/v1` and the owning Settings/Security services.

## Backend authority

Root: `src/Modules/Settings/`, wired by `SettingsModule.php`. The backend module remains the configuration/security authority even while presentation moves into the Services Station Settings tab.

All browser-facing settings operations use `qsd/v1/admin/settings/*` and `PlatformAccess::CAP`; secret mutation additionally requires administrator authority.

### Credential storage

- `Connections/ConnectionProviderDefinition.php` declares provider fields and brokered scopes.
- `Connections/ConnectionStore.php` is the sole reader/writer of the non-autoloaded `qsd_settings_connections` option.
- `Connections/ConnectionProviders.php` registers providers; Rezdy is currently the only one.
- Stored secrets are encrypted envelopes, never plaintext.

### Encryption and secret authority

- `Security/CredentialCipher.php` seals provider secrets with XChaCha20-Poly1305 under external `QSD_CREDENTIAL_KEY`.
- Missing/invalid key material fails closed.
- `Security/CredentialAuthority.php` uses existing `manage_options` authority for set/replace/clear/disconnect.
- Safe state/non-secret configuration remains available under `manage_qsd`.
- REST projection reports safe state only, such as configured/encryption/permission state.

### Request-key broker

- `Security/CredentialBroker.php` issues short-lived, single-use request keys bound to provider, scope, caller, WordPress user and optional subject.
- `Security/WpdbRequestKeyStore.php` stores only hashes and consumes with an atomic delete.
- `Connections/ConnectorCredentials.php` is internal; only the broker may obtain decrypted provider secrets.
- Provider operations execute server-side and consumers receive only safe operation results.
- `BrokerAuditLog` records bounded safe metadata and never request keys, hashes, encryption keys or provider secrets.

### Rotation/re-seal

- `Security/CredentialRotation.php` re-seals all stored provider secrets from `QSD_CREDENTIAL_KEY_PREVIOUS` to `QSD_CREDENTIAL_KEY`, all-or-nothing.
- Current accepted execution is shell-only through `wp qsd credentials reseal`.
- There is no REST rotation route.
- The future Security UI may expose safe readiness/status/operator guidance, but must not add a browser/REST rotation trigger without a separate Owner/Reviewer approval.

## Approved presentation roadmap

### Phase 2A — runtime/API validation

Validate encryption, storage, server-derived caller/user identity, request-key consume/replay/expiry/binding, permissions, audit, rotation/re-seal and one controlled provider authentication/connection check on a real non-production WordPress runtime.

The database is evidence/storage infrastructure only. Do not create a direct SQL/query platform path or bypass `qsd/v1`, the broker, or owning stores.

### Phase 2B — Services Station Settings placement

Move the presentation into `Services Station → Settings → General | Tools | Security`.

Do not create a second Settings system. Retire the standalone Settings presentation only after replacement parity exists.

### Phase 2C — Security → API Keys UI

The Security surface must provide:

- provider name/identity and environment;
- configured/not-configured state;
- encryption available/unavailable state;
- administrator permission state;
- write-only add/replace API-key flow;
- explicit clear/disconnect with confirmation;
- safe success/failure notifications;
- no secret, request key/hash, encryption key or key fingerprint projected into browser state, REST responses, audit UI or logs.

Rezdy importer belongs under Tools; Rezdy API credentials belong under Security → API Keys.

### Mandatory Owner UI gate

As soon as `Services Station → Settings → Security → API Keys` is browser-ready, stop and notify the Owner.

Set the active work state to `BLOCKED — OWNER UI REVIEW REQUIRED`, provide the exact candidate SHA/runtime surface, and wait for explicit Owner acceptance/corrections.

A normal `run the cycle`, `continue the work`, or equivalent instruction must **not** move work past this gate.

### Phase 2D — rotation operator flow

After explicit Owner UI acceptance, finish safe rotation readiness/status/operator guidance around the existing shell-only re-seal operation.

Do not add REST/browser execution of master-key rotation without a separate approval.

### Phase 2E — closeout

Update this map and any changed contract to landed reality, formalise the reusable `Settings → General | Tools | Security` pattern, remove stale presentation naming, run `npm test` and `npm run docs:check`, then obtain Reviewer acceptance before Phase 3.

## Service Meta

`ServiceMeta/ServiceMetaSchema.php` owns field definitions in `qsd_settings_service_meta_schema`; `Http/ServiceMetaSchemaController.php` exposes list/create/update/reorder/retire/restore.

Field and option identity remains scoped and server-minted as already defined by the Service Meta schema contract. This work is separate from the Security presentation migration.

## Current frontend reality

The present implementation under `resources/ts/settings-station/` remains the accepted foundation until Phase 2B replaces its presentation:

- `register.ts` currently registers standalone Settings navigation/destination.
- `presentation/SettingsDeck.tsx` currently hosts Connections/Security and Service Meta lanes.
- `presentation/SettingsConnectionsLane.tsx` currently presents safe credential state/write-only secret controls.
- `api.ts` is the frontend settings endpoint module.

Treat those paths as current implementation, not the final Station placement.

## Validation

From `wp-content/plugins/qsd-platform/`:

- `npm test`
- `npm run docs:check`

Relevant current tests include settings connections, credential broker, credential rotation, Service Meta schema, Settings Station contract and Settings-home regression.
