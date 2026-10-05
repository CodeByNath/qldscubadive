# Settings Station

Settings is the platform/business configuration authority. It owns the Connections/Security Tool and the Service Meta field-definition schema. It owns no domain record, no lifecycle, no drawer, and no Platform ID family, and it is unrelated to Service Home's `Settings` lane (Create Service / Create Category).

## Backend

Root: `src/Modules/Settings/`, wired by [SettingsModule.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/SettingsModule.php) from `Core\Plugin`. Every route is `qsd/v1/admin/settings/*`, gated by `PlatformAccess::CAP`.

- **Connections.** [ConnectionProviderDefinition.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviderDefinition.php) is the provider-neutral declaration (`text`, `select`, `secret` fields). [ConnectionProviders.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviders.php) lists Connectors with a real consumer — Rezdy only. [ConnectionStore.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionStore.php) is the sole reader/writer of the non-autoloaded `qsd_settings_connections` option.
- **Secret contract.** [SettingsConnectionsController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/SettingsConnectionsController.php) accepts a secret on PUT and never returns it: secret fields project only `configured`. An empty secret keeps the stored value; `clear` removes it; DELETE disconnects.
- **Consumer capability.** [ConnectorCredentials.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectorCredentials.php) is the only server-side way a Connector reads its own provider values. [RezdyConnector.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connectors/RezdyConnector.php) declares Rezdy's fields and reports connection state; it makes no HTTP call and maps nothing.
- **Service Meta schema.** [ServiceMetaSchema.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/ServiceMeta/ServiceMetaSchema.php) owns field definitions in the `qsd_settings_service_meta_schema` option; [ServiceMetaSchemaController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/ServiceMetaSchemaController.php) exposes list, create, update, reorder, retire, and restore — no delete.

## Service Meta identity and policy

Types: text, textarea, number, boolean, select, image, gallery, repeater (sub-fields of any non-repeater type). A field is a rung-2 scoped child of the schema: its `fld_…` id, and each option's `opt_…` id, is minted server-side and never derived from label, slug, or position; order is presentation only. Removal is **retire/restore**; type is immutable; existing option and sub-field ids cannot be dropped, so stored Service values never orphan. Per-Service values stay with Service Station — see the [Service Meta schema contract](../architecture/service-meta-schema-contract.md).

## Frontend

Root: `resources/ts/settings-station/`. [register.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/register.ts) registers the `settings` navigation row (order 90), destination, the empty `settings-home` source, and the `settings-deck` kit; Admin places the binding in `registerPresentationPolicy()`. [SettingsDeck.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsDeck.tsx) hosts two lanes on the shared tab set: [SettingsConnectionsLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsConnectionsLane.tsx) (write-only secret inputs, `useInlineConfirm` Disconnect) and [ServiceMetaSchemaLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/ServiceMetaSchemaLane.tsx) with its editor. Lanes call only `useSettingsConnections` / `useServiceMetaSchema`; [api.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/api.ts) is the one endpoint module.

## Open decision gates

- **Rotating request keys** — short-lived, scoped, single-use keys need a security decision; Connectors get the stored credential server-side only.
- **Secret encryption at rest** — secrets are stored as plain option values.
- **Credential permission** — managing secrets uses the platform capability, not a stricter one.
- **Field purge** — permanent removal needs an approved Service-value recovery rule.
- **Rezdy importer** — waits for the Owner's pre-built importer.

## Validation

From the plugin root: `npm test` (includes `tests/settings-connections.php`, `tests/settings-service-meta-schema.php`, `contract:settings-station`, `regression:settings-home`) and `npm run docs:check`.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station Navigation](admin-station-navigation.md), [Station Tab Set](station-tab-set.md), and [Service Station](service-station.md).
