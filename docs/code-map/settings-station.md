# Settings Station

Settings is the platform and business configuration authority. It owns:

- the Connections/Security Tool, which is a credential broker;
- the Element definition schema, shown as the "Service Meta" Tool.

It owns no domain record, no lifecycle, no drawer, and no Platform ID family. It is unrelated to Service Home's `Settings` lane (Create Service / Create Category).

## Backend

Root: `src/Modules/Settings/`, wired by [SettingsModule.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/SettingsModule.php) from `Core\Plugin`. Every route is under `qsd/v1/admin/settings/*` and gated by `PlatformAccess::CAP`.

- **Connections.**
  - [ConnectionProviderDefinition.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviderDefinition.php) declares a provider's fields and brokered `scopes`.
  - [ConnectionStore.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionStore.php) is the sole reader and writer of `qsd_settings_connections`. Secrets in it are encrypted envelopes.
  - [ConnectionProviders.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviders.php) lists the providers. Rezdy is the only one.
- **Encryption at rest.** [CredentialCipher.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialCipher.php) seals secrets with XChaCha20-Poly1305 under the `QSD_CREDENTIAL_KEY` wp-config constant and fails closed when there is no key.
  - [SettingsConnectionsController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/SettingsConnectionsController.php) seals secrets on PUT and refuses with 409 when there is no key.
  - Its projection reports only `configured`, and only when the secret decrypts. The list reports `encryption.available`.
- **Credential broker.** [CredentialBroker.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialBroker.php) issues short-lived, single-use request keys bound to provider, scope, caller, user and subject.
  - Keys are stored as hashes by [WpdbRequestKeyStore.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/WpdbRequestKeyStore.php) and consumed with an atomic row delete.
  - The broker performs the provider's `BrokeredProviderOperation` server-side. Consumers never receive a secret.
  - Outcomes are recorded by `BrokerAuditLog`.
  - [ConnectorCredentials.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectorCredentials.php) is internal to Settings. Only the broker reads a decrypted secret.
  - [RezdyConnector.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connectors/RezdyConnector.php) declares no brokered scope, makes no HTTP call, and maps nothing.
  - See the [Credential broker contract](../architecture/credential-broker-contract.md).
- **Element definitions.**
  - [ServiceMetaSchema.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/ServiceMeta/ServiceMetaSchema.php) owns `qsd_settings_service_meta_schema`.
  - [ServiceMetaSchemaController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/ServiceMetaSchemaController.php) exposes list, create, update, reorder, retire and restore. There is no delete.
  - Service reads definitions only through the read-only [ServiceElementDefinitions.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/ServiceMeta/ServiceElementDefinitions.php).

## Definition identity and policy

- **Types.** text, textarea, number, boolean, select, image, gallery, group and repeater. Group and repeater are containers; their sub-fields are non-container types.
- **Ids.** A definition is a rung-2 child of the schema. Its `fld_…` id, and each option's `opt_…` id, is minted server-side. Order is presentation only.
- **Changes.** Removal means retire and restore. A field's type is immutable. Option and sub-field ids cannot be dropped.
- **Instances.** Instances belong to Service: see [Service Elements](service-elements.md).

## Frontend

Root: `resources/ts/settings-station/`.

- [register.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/register.ts) registers the `settings` navigation row, the destination, the empty `settings-home` source, and the `settings-deck` kit.
- [SettingsDeck.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsDeck.tsx) hosts two lanes:
  - [SettingsConnectionsLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsConnectionsLane.tsx) has write-only secrets, a no-encryption-key warning, and a `useInlineConfirm` Disconnect.
  - [ServiceMetaSchemaLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/ServiceMetaSchemaLane.tsx) carries its editor, including group sub-fields.
- [api.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/api.ts) is the one endpoint module.

## Open decision gates

- **Credential permission.** Managing credentials uses `manage_qsd`. A narrower capability needs an Owner decision.
- **Key operations.** Provisioning and rotating `QSD_CREDENTIAL_KEY` on staging and production.
- **Real credentials.** These are prohibited until this broker, encryption and permission are reviewed.
- **Field purge.** Permanent removal needs an approved recovery rule.
- **Rezdy importer.** Brokered Rezdy scopes and operations wait for the Owner's pre-built importer.

## Validation

From the plugin root: `npm test` and `npm run docs:check`. `npm test` includes:

- `tests/settings-connections.php`;
- `tests/settings-credential-broker.php`;
- `tests/settings-service-meta-schema.php`;
- `contract:settings-station`;
- `regression:settings-home`.

## Related Code Maps

[Service Elements](service-elements.md), [Station Manager](station-manager.md), [Admin Station Navigation](admin-station-navigation.md), [Station Tab Set](station-tab-set.md).
