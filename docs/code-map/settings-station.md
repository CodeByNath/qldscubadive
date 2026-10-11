# Settings and Security

Settings is a reusable tab pattern inside a Station, not a separate Station identity or domain record. The standalone Settings navigation and deck are retired. Each section is now presented by one Station only:

```text
Services Station              Account Station
├─ Details                    └─ Settings
├─ Connections                   ├─ Tools
└─ Settings                      │  ├─ Rezdy importer
   └─ General                    │  └─ future operational tools
      └─ Service fields          └─ Security
                                     ├─ API keys / credentials
                                     ├─ encryption / rotation
                                     ├─ request-key broker
                                     └─ permissions / audit
```

Other Stations may surface `General`, `Tools` and/or `Security` — the pattern is reusable. Shared data is owned once by the platform authority; placement never duplicates persistence or transfers authority. `Tools`/`Security` are reachable from [Account Station](account-station.md) only — the actual relocation, not an additive path: Services' tab presents `General` (Service fields) only, an Owner-excluded metafield; its backend/routes/access are unchanged.

## Ownership boundaries

- `Services Station → Connections` stays Service-domain relationships.
- `Settings → Tools` holds operational tools and importers (Rezdy importer).
- `Settings → Security` owns API keys and credential/security controls.
- Tools consume Security-governed authority and never own or read long-lived credentials.
- The database is storage only. Browser consumers go through `qsd/v1` and the owning Settings/Security services.

## Backend authority

Root `src/Modules/Settings/`, wired by [`SettingsModule.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/SettingsModule.php). Every route is `qsd/v1/admin/settings/*` behind `PlatformAccess::CAP`; secret mutation also needs administrator authority.

- **Storage.** `Connections/ConnectionProviderDefinition.php` declares fields and brokered scopes. `Connections/ConnectionStore.php` is the sole reader/writer of the non-autoloaded `qsd_settings_connections` option. Rezdy is the only provider. Secrets are encrypted envelopes, never plaintext.
- **Encryption and authority.** `Security/CredentialCipher.php` seals with XChaCha20-Poly1305 and fails closed. `Security/CredentialKeyring.php` owns the key: QSD generates it on first save and stores it sealed under a wrapping key derived from the site's WordPress secret keys; admins never handle key material. `Security/CredentialAuthority.php` (`manage_options`) gates set/replace/clear/disconnect; safe state needs only `manage_qsd`.
- **Request-key broker.** `Security/CredentialBroker.php` issues short-lived, single-use keys bound to provider, scope, caller, WordPress user and optional subject; the caller must be on the server-side allow-list (`SettingsModule::brokerCallers()`). `Security/WpdbRequestKeyStore.php` stores hashes only and consumes with an atomic delete. Only the broker obtains a decrypted secret, through `Connections/ConnectorCredentials.php`.
- **Provider check.** `Connectors/RezdyConnector.php` declares one scope, `connection.verify`. [`RezdyConnectionCheck.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connectors/RezdyConnectionCheck.php) performs it: one read-only GET to the Rezdy staging API, returning outcome, HTTP status and latency only. Other environments are refused without a request.
- **Runtime validation.** [`BrokerValidation.php`](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/BrokerValidation.php), served by `Http/SettingsSecurityController.php` at `POST admin/settings/security/broker-validation` (administrators only). It checks encrypted storage, rotation readiness, the request-key lifecycle (issue, consume, replay, expiry, binding burn, sweep), audit, and makes one provider call. User and caller are server-derived; the request body is ignored; the report is leak-guarded.
- **Rotation.** `Security/CredentialRotation.php` moves every secret to a new data key, all or nothing, from `POST admin/settings/security/rotation` (administrators). It and every connection write share one guard, `Security/WpdbCredentialMutationGuard.php` (a connection-owned database named lock; ownership proven before each write). Its read-only `inspect()` feeds the validation report.
- **Tool access.** `SettingsModule::brokeredAccess()` gives a Tool a `Security/BrokeredAccess.php` bound to its allow-listed caller; a Tool never holds a key or credential.

See the [Credential broker contract](../architecture/credential-broker-contract.md).

## Roadmap

Phases 2A–2E are defined in the [roadmap](../roadmap.md). The Owner accepted the API Keys placement on 2026-10-06 and corrected the credential flow to the one above.

## Service Meta

`ServiceMeta/ServiceMetaSchema.php` owns field definitions in `qsd_settings_service_meta_schema`; `Http/ServiceMetaSchemaController.php` offers list, create, update, reorder, retire and restore. Field and option identity is scoped and server-minted, per the [Service Meta schema contract](../architecture/service-meta-schema-contract.md).

## Frontend

- **Pattern.** `station-manager/registry/stationSettings.ts` holds contributions; [`StationSettings.tsx`](../../wp-content/plugins/qsd-platform/resources/ts/admin-station/presentation/StationSettings.tsx) renders a Station's sections. Service's `ServiceSettingsLane.tsx` hosts it for `services`, and Service registers `ServiceCreateLaunchers` under General.
- **Settings peer** (`resources/ts/settings-station/`). `register.ts` contributes three panels: General → `ServiceMetaSchemaLane` (`services` only), Tools → `RezdyImporterTool` (not available yet, `account` only), Security → [`SecurityApiKeysPanel.tsx`](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SecurityApiKeysPanel.tsx) (`account` only). Only each contribution's `stationIds` array changed in the relocation; the panel, data, and backend routes below are identical either way.
- **API Keys.** It shows secure-storage and access state, Rotate encryption key for administrators, plus one card per provider with environment and key state. A key is typed into a password input that exists only while adding or replacing, and is cleared on save. Remove and Disconnect confirm in place. Test connection runs the validation route. A `manage_qsd` user sees safe state only.
- `useSettingsConnections.ts` holds state and actions; `api.ts` is the single endpoint module.

## Validation

From `wp-content/plugins/qsd-platform/`: `npm test` and `npm run docs:check`. Relevant tests: `tests/settings-connections.php`, `tests/settings-credential-broker.php`, `tests/settings-credential-rotation.php`, `tests/settings-security-validation.php`, `tests/settings-service-meta-schema.php`, `contract:settings-station`, `regression:services-settings` (General) and `regression:account-settings` (Tools/Security, relocated from the former Services-mounted regression — same panels and backend).

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station Navigation](admin-station-navigation.md), [Station Tab Set](station-tab-set.md) and [Service Station](service-station.md).
