# Settings Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`SettingsModule.php` wires the Settings Station's admin REST controllers. Settings is configuration authority, not domain authority: no domain record, lifecycle, or Platform ID family lives here.

- `Connections/` — the provider-neutral connection model. `ConnectionStore` is the sole reader/writer of the `qsd_settings_connections` option (secrets stored as encrypted envelopes); `ConnectorCredentials` is Settings-internal and only `Security\CredentialBroker` reads a decrypted secret; `ConnectionProviders` lists Connectors with a real consumer.
- `Security/` — `CredentialCipher` (XChaCha20-Poly1305 under the `QSD_CREDENTIAL_KEY` platform master key, an operator-provisioned server constant that is never managed in the UI or by a business admin; fail closed), `CredentialBroker` (short-lived single-use bound request keys, hash-only `WpdbRequestKeyStore`, `BrokerAuditLog`, Settings-owned `BrokeredProviderOperation`s), `CredentialAuthority` (`manage_options` for any secret change), and `CredentialRotation` (all-or-nothing re-seal, shell-only via `wp qsd credentials reseal`).
- `Connectors/RezdyConnector.php` — the first Connector: configuration fields, connection state, and one brokered scope `connection.verify`, performed by `RezdyConnectionCheck` (one read-only GET, Rezdy staging API only, safe outcome metadata).
- `Security/BrokerValidation.php` + `Http/SettingsSecurityController.php` — the Phase 2 runtime validation run (administrators only; server-derived user and allow-listed caller from `SettingsModule::brokerCallers()`; request body ignored).
- `ServiceMeta/` — Service Meta field definitions (`ServiceMetaSchema`, sole owner of its option).
- `Http/` — `SettingsConnectionsController` (secrets sealed in, never out) and `ServiceMetaSchemaController` (no delete route).

## Boundaries

Never return a stored secret from any route or projection, never store one unsealed, and never hand a decrypted secret to a consumer — consumers go through the broker. Secret changes need `CredentialAuthority`, not only the platform capability; key rotation never gets a REST route. Never read Service storage or write Service values here — per-Service values belong to `Modules/Service`. Do not add importer or mapping behaviour, further brokered scopes, or provider calls beyond `RezdyConnectionCheck` before the Owner's pre-built Rezdy importer is reviewed. Never take a broker grant's user or caller from client input. Provider API keys enter only through the write-only UI/`qsd/v1` path; never ask a user to put a provider key or Security setup in `wp-config.php` or any server file. Do not mint Platform IDs for credentials, request keys, field definitions, options, or rows. The admin capability is `Core\PlatformAccess::CAP`.

Read [Settings Station](../../../../../../docs/code-map/settings-station.md), the [Credential broker contract](../../../../../../docs/architecture/credential-broker-contract.md), and the [Service Meta schema contract](../../../../../../docs/architecture/service-meta-schema-contract.md).

## Validation

From the plugin root: `npm test` (includes `tests/settings-connections.php`, `tests/settings-credential-broker.php`, `tests/settings-credential-rotation.php`, `tests/settings-security-validation.php`, and `tests/settings-service-meta-schema.php`) and `npm run docs:check`.
