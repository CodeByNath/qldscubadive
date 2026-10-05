# Settings Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`SettingsModule.php` wires the Settings Station's admin REST controllers. Settings is configuration authority, not domain authority: no domain record, lifecycle, or Platform ID family lives here.

- `Connections/` — the provider-neutral connection model. `ConnectionStore` is the sole reader/writer of the `qsd_settings_connections` option; `ConnectorCredentials` is the only server-side read path for a Connector's own values; `ConnectionProviders` lists Connectors with a real consumer.
- `Connectors/RezdyConnector.php` — the first Connector: configuration fields and connection state only.
- `ServiceMeta/` — Service Meta field definitions (`ServiceMetaSchema`, sole owner of its option).
- `Http/` — `SettingsConnectionsController` (secrets in, never out) and `ServiceMetaSchemaController` (no delete route).

## Boundaries

Never return a stored secret from any route or projection. Never read Service storage or write Service values here — per-Service values belong to `Modules/Service`. Do not add importer, mapping, or provider HTTP calls to `RezdyConnector` before the Owner's pre-built Rezdy importer is reviewed. Do not mint Platform IDs for credentials, field definitions, options, or rows. The admin capability is `Core\PlatformAccess::CAP`.

Read [Settings Station](../../../../../../docs/code-map/settings-station.md) and the [Service Meta schema contract](../../../../../../docs/architecture/service-meta-schema-contract.md).

## Validation

From the plugin root: `npm test` (includes `tests/settings-connections.php` and `tests/settings-service-meta-schema.php`) and `npm run docs:check`.
