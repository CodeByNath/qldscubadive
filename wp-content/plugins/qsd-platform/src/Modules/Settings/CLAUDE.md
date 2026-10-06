# Settings Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`SettingsModule.php` wires the Settings admin REST controllers, which are presented in a Station's `Settings → General | Tools | Security` tab. Settings is configuration authority, not domain authority: no domain record, lifecycle, or Platform ID family lives here.

- `Connections/` — the provider-neutral connection model. `ConnectionStore` is the sole reader/writer of the `qsd_settings_connections` option (secrets stored as encrypted envelopes); `ConnectorCredentials` is Settings-internal and only `Security\CredentialBroker` reads a decrypted secret; `ConnectionProviders` lists Connectors with a real consumer.
- `Security/` — `CredentialCipher` (XChaCha20-Poly1305 AEAD primitive; fail closed), `CredentialKeyring` (QSD's own data key, generated on the first secret save and stored only sealed under a wrapping key derived from the site's WordPress secret keys; no setup step, never an admin task), `CredentialBroker` (short-lived single-use bound request keys, hash-only `WpdbRequestKeyStore`, `BrokerAuditLog`, Settings-owned `BrokeredProviderOperation`s), `CredentialAuthority` (`manage_options` for any secret change), and `CredentialRotation` (QSD-owned, all-or-nothing move of every secret to a new data key).
- `Connectors/RezdyConnector.php` — the first Connector: configuration fields, connection state, and one brokered scope `connection.verify`, performed by `RezdyConnectionCheck` (one read-only GET, Rezdy staging API only, safe outcome metadata).
- `Security/BrokerValidation.php` + `Http/SettingsSecurityController.php` — the Phase 2 runtime validation run and the rotation route (administrators only; server-derived user and allow-listed caller from `SettingsModule::brokerCallers()`; request bodies ignored).
- `ServiceMeta/` — Service Meta field definitions (`ServiceMetaSchema`, sole owner of its option).
- `Http/` — `SettingsConnectionsController` (secrets sealed in, never out) and `ServiceMetaSchemaController` (no delete route).

## Boundaries

Never return a stored secret from any route or projection, never store one unsealed, and never hand a decrypted secret to a consumer — consumers go through the broker. A Tool receives only `SettingsModule::brokeredAccess()` for its allow-listed caller, never the broker, a request key or `ConnectorCredentials`. Secret changes and rotation need `CredentialAuthority`, not only the platform capability; no route ever accepts or returns key material, and no credential or key step needs the shell. Never read Service storage or write Service values here — per-Service values belong to `Modules/Service`. Do not add importer or mapping behaviour, further brokered scopes, or provider calls beyond `RezdyConnectionCheck` before the Owner's pre-built Rezdy importer is reviewed. Never take a broker grant's user or caller from client input. Provider API keys enter only through the write-only UI/`qsd/v1` path; never ask a user to put a provider key or Security setup in `wp-config.php` or any server file. Do not mint Platform IDs for credentials, request keys, field definitions, options, or rows. The admin capability is `Core\PlatformAccess::CAP`.

Read [Settings and Security](../../../../../../docs/code-map/settings-station.md), the [Credential broker contract](../../../../../../docs/architecture/credential-broker-contract.md), and the [Service Meta schema contract](../../../../../../docs/architecture/service-meta-schema-contract.md).

## Validation

From the plugin root: `npm test` (includes `tests/settings-connections.php`, `tests/settings-credential-broker.php`, `tests/settings-credential-rotation.php`, `tests/settings-security-validation.php`, and `tests/settings-service-meta-schema.php`) and `npm run docs:check`.
