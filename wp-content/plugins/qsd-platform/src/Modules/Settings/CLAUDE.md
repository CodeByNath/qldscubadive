# Settings Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`SettingsModule.php` wires the Settings Station's admin REST controllers. Settings is configuration authority, not domain authority: no domain record, lifecycle, or Platform ID family lives here.

- `Connections/` — the provider-neutral connection model. `ConnectionStore` is the sole reader/writer of the `qsd_settings_connections` option (secrets stored as encrypted envelopes); `ConnectorCredentials` is Settings-internal and only `Security\CredentialBroker` reads a decrypted secret; `ConnectionProviders` lists Connectors with a real consumer.
- `Security/` — `CredentialCipher` (XChaCha20-Poly1305 under the `QSD_CREDENTIAL_KEY` wp-config constant, fail closed), `CredentialBroker` (short-lived single-use bound request keys, hash-only `WpdbRequestKeyStore`, `BrokerAuditLog`, Settings-owned `BrokeredProviderOperation`s).
- `Connectors/RezdyConnector.php` — the first Connector: configuration fields and connection state only; no brokered scope.
- `ServiceMeta/` — Element definitions (`ServiceMetaSchema`, sole owner of its option) and the read-only `ServiceElementDefinitions` capability Service consumes.
- `Http/` — `SettingsConnectionsController` (secrets sealed in, never out) and `ServiceMetaSchemaController` (no delete route).

## Boundaries

Never return a stored secret from any route or projection, never store one unsealed, and never hand a decrypted secret to a consumer — consumers go through the broker. Never read Service storage or write Service Element values here — instances belong to `Modules/Service`. Do not add importer, mapping, brokered scopes, or provider HTTP calls to `RezdyConnector` before the Owner's pre-built Rezdy importer is reviewed. Do not mint Platform IDs for credentials, request keys, definitions, options, or rows. The admin capability is `Core\PlatformAccess::CAP`.

Read [Settings Station](../../../../../../docs/code-map/settings-station.md), the [Credential broker contract](../../../../../../docs/architecture/credential-broker-contract.md), and the [Service Element composition contract](../../../../../../docs/architecture/service-element-composition-contract.md).

## Validation

From the plugin root: `npm test` (includes `tests/settings-connections.php`, `tests/settings-credential-broker.php`, and `tests/settings-service-meta-schema.php`) and `npm run docs:check`.
