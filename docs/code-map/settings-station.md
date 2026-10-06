# Settings and Security

Settings is a reusable tab pattern inside a Station, not a separate Station identity or domain record. It is presented inside the Services Station; the standalone Settings navigation and deck are retired:

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

Other Stations may surface `General`, `Tools` and/or `Security`. Shared data is owned once by the platform authority; placement never duplicates persistence or transfers authority.

## Ownership boundaries

- `Services Station → Connections` stays Service-domain relationships.
- `Settings → Tools` holds operational tools and importers (Rezdy importer).
- `Settings → Security` owns API keys and credential/security controls.
- Tools consume Security-governed authority and never own or read long-lived credentials.
- The database is storage only. Browser consumers go through `qsd/v1` and the owning Settings/Security services.

## Backend authority

Root `src/Modules/Settings/`, wired by [`SettingsModule.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/SettingsModule.php). Every route is `qsd/v1/admin/settings/*` behind `PlatformAccess::CAP`; secret mutation also needs administrator authority.

- **Storage.** `Connections/ConnectionProviderDefinition.php` declares fields and brokered scopes. `Connections/ConnectionStore.php` is the sole reader/writer of the non-autoloaded `qsd_settings_connections` option. Rezdy is the only provider. Secrets are encrypted envelopes, never plaintext.
- **Encryption and authority.** `Security/CredentialCipher.php` seals with XChaCha20-Poly1305 under `QSD_CREDENTIAL_KEY` and fails closed. That is the platform master key, operator-provisioned outside the database and never managed in the UI; provider API keys are entered only through the UI. `Security/CredentialAuthority.php` (`manage_options`) gates set/replace/clear/disconnect; safe state and non-secret configuration need only `manage_qsd`.
- **Request-key broker.** `Security/CredentialBroker.php` issues short-lived, single-use keys bound to provider, scope, caller, WordPress user and optional subject. The caller must be on the server-side allow-list (`SettingsModule::brokerCallers()`). `Security/WpdbRequestKeyStore.php` stores hashes only and consumes with an atomic delete. Only the broker obtains a decrypted secret through `Connections/ConnectorCredentials.php`. `BrokerAuditLog` records bounded safe metadata.
- **Provider check.** `Connectors/RezdyConnector.php` declares one scope, `connection.verify`. [`RezdyConnectionCheck.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connectors/RezdyConnectionCheck.php) performs it: one read-only GET to the Rezdy staging API, returning outcome, HTTP status and latency only. Other environments are refused without a request.
- **Runtime validation.** [`BrokerValidation.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/BrokerValidation.php), served by `Http/SettingsSecurityController.php` at `POST admin/settings/security/broker-validation` (administrators only). It checks encrypted storage, rotation readiness, the request-key lifecycle (issue, consume, replay, expiry, binding burn, sweep), audit, and makes one provider call. User and caller are server-derived; the request body is ignored; the report is leak-guarded.
- **Rotation.** `Security/CredentialRotation.php` re-seals all secrets from `QSD_CREDENTIAL_KEY_PREVIOUS` to `QSD_CREDENTIAL_KEY`, all or nothing, only via `wp qsd credentials reseal`. Its read-only `inspect()` feeds the validation report. There is no REST rotation trigger.

See the [Credential broker contract](../architecture/credential-broker-contract.md).

## Roadmap and Owner gate

Phases 2A–2E are defined in the [roadmap](../roadmap.md). When API Keys is browser-ready, work stops at `BLOCKED — OWNER UI REVIEW REQUIRED`. A normal cycle request never crosses that gate.

## Service Meta

`ServiceMeta/ServiceMetaSchema.php` owns field definitions in `qsd_settings_service_meta_schema`; `Http/ServiceMetaSchemaController.php` offers list, create, update, reorder, retire and restore. Field and option identity is scoped and server-minted, per the [Service Meta schema contract](../architecture/service-meta-schema-contract.md).

## Frontend

- **Pattern.** `station-manager/registry/stationSettings.ts` holds contributions; [`StationSettings.tsx`](../../wp-content/plugins/qsd-platform/resources/ts/admin-station/presentation/StationSettings.tsx) renders a Station's sections. Service's `ServiceSettingsLane.tsx` hosts it for `services`, and Service registers `ServiceCreateLaunchers` under General.
- **Settings peer** (`resources/ts/settings-station/`). `register.ts` contributes three panels to `services`: General → `ServiceMetaSchemaLane` (Service fields), Tools → `RezdyImporterTool` (not available yet), Security → [`SecurityApiKeysPanel.tsx`](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SecurityApiKeysPanel.tsx).
- **API Keys.** It shows secure-storage and access state, plus one card per provider with environment and key state. A key is typed into a password input that exists only while adding or replacing, and is cleared on save. Remove and Disconnect confirm in place. Test connection runs the validation route. A `manage_qsd` user sees safe state only.
- `useSettingsConnections.ts` holds state and actions; `api.ts` is the single endpoint module.

## Validation

From `wp-content/plugins/qsd-platform/`: `npm test` and `npm run docs:check`. Relevant tests: `tests/settings-connections.php`, `tests/settings-credential-broker.php`, `tests/settings-credential-rotation.php`, `tests/settings-security-validation.php`, `tests/settings-service-meta-schema.php`, `contract:settings-station` and `regression:services-settings`.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station Navigation](admin-station-navigation.md), [Station Tab Set](station-tab-set.md) and [Service Station](service-station.md).
