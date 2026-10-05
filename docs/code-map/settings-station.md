# Settings Station

Settings is the platform and business configuration authority. It owns:

- the Connections/Security Tool, which is a credential broker;
- the Service Meta field-definition schema.

It owns no domain record, no lifecycle, no drawer, and no Platform ID family. It is unrelated to Service Home's `Settings` lane (Create Service / Create Category).

## Backend

Root: `src/Modules/Settings/`, wired by [SettingsModule.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/SettingsModule.php) from `Core\Plugin`. Every route is under `qsd/v1/admin/settings/*` and gated by `PlatformAccess::CAP`. Changing a secret also needs administrator authority (see Permission).

- **Connections.**
  - [ConnectionProviderDefinition.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviderDefinition.php) declares a provider's fields and brokered `scopes`.
  - [ConnectionStore.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionStore.php) is the sole reader and writer of the non-autoloaded `qsd_settings_connections` option. Secrets in it are encrypted envelopes.
  - [ConnectionProviders.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectionProviders.php) lists the providers. Rezdy is the only one.
- **Encryption at rest.** [CredentialCipher.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialCipher.php) seals secrets with XChaCha20-Poly1305 under the `QSD_CREDENTIAL_KEY` wp-config constant and fails closed when there is no key.
  - [SettingsConnectionsController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/SettingsConnectionsController.php) seals secrets on PUT and refuses with 409 when there is no key. An empty secret keeps the stored value; `clear` removes it; DELETE disconnects.
  - Its projection reports only `configured`, and only when the secret decrypts. The list reports `encryption.available` and `permissions.manage_secrets`.
- **Permission.** [CredentialAuthority.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialAuthority.php) is the existing administrator capability `manage_options`.
  - Setting, replacing or clearing a secret, and disconnecting, need it.
  - Reading safe state and saving non-secret configuration need only `manage_qsd`.
- **Key rotation.** [CredentialRotation.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialRotation.php) re-seals every secret from `QSD_CREDENTIAL_KEY_PREVIOUS` to `QSD_CREDENTIAL_KEY`, all or nothing.
  - It runs only from the shell as `wp qsd credentials reseal` ([CredentialRotationCommand.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialRotationCommand.php)). There is no REST route.
- **Credential broker.** [CredentialBroker.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/CredentialBroker.php) issues short-lived, single-use request keys bound to provider, scope, caller, user and subject.
  - Keys are stored as hashes by [WpdbRequestKeyStore.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Security/WpdbRequestKeyStore.php) and consumed with an atomic row delete.
  - The broker performs the provider's `BrokeredProviderOperation` server-side. Consumers never receive a secret.
  - Outcomes are recorded by `BrokerAuditLog`.
  - [ConnectorCredentials.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connections/ConnectorCredentials.php) is internal to Settings. Only the broker reads a decrypted secret.
  - [RezdyConnector.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Connectors/RezdyConnector.php) declares no brokered scope, makes no HTTP call, and maps nothing.
  - See the [Credential broker contract](../architecture/credential-broker-contract.md).
- **Service Meta schema.** [ServiceMetaSchema.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/ServiceMeta/ServiceMetaSchema.php) owns field definitions in the `qsd_settings_service_meta_schema` option; [ServiceMetaSchemaController.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/Http/ServiceMetaSchemaController.php) exposes list, create, update, reorder, retire, and restore — no delete.

## Service Meta identity and policy

Types: text, textarea, number, boolean, select, image, gallery, repeater (sub-fields of any non-repeater type). A field is a rung-2 scoped child of the schema: its `fld_…` id, and each option's `opt_…` id, is minted server-side and never derived from label, slug, or position; order is presentation only. Removal is **retire/restore**; type is immutable; existing option and sub-field ids cannot be dropped, so stored Service values never orphan. Per-Service values stay with Service Station — see the [Service Meta schema contract](../architecture/service-meta-schema-contract.md).

## Frontend

Root: `resources/ts/settings-station/`.

- [register.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/register.ts) registers the `settings` navigation row (order 90), the destination, the empty `settings-home` source, and the `settings-deck` kit. Admin places the binding in `registerPresentationPolicy()`.
- [SettingsDeck.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsDeck.tsx) hosts two lanes on the shared tab set:
  - [SettingsConnectionsLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/SettingsConnectionsLane.tsx) has write-only secrets, a no-encryption-key warning, and a `useInlineConfirm` Disconnect. Without `manage_secrets`, secret inputs are read-only and Disconnect is not offered.
  - [ServiceMetaSchemaLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/presentation/ServiceMetaSchemaLane.tsx) carries its editor.
- Lanes call only `useSettingsConnections` / `useServiceMetaSchema`; [api.ts](../../wp-content/plugins/qsd-platform/resources/ts/settings-station/api.ts) is the one endpoint module.

## Open decision gates

- **Real credentials and key provisioning.** No real credential is stored and no key is provisioned on staging or production until the Phase 2 validation package.
- **Element-definition identity.** Whether reusable definitions need their own Platform ID family is deferred to the Service Manager phase.
- **Field purge.** Permanent removal needs an approved Service-value recovery rule.
- **Rezdy importer.** Brokered Rezdy scopes and operations are Phase 3 work, after the Owner's importer reference is audited.

## Validation

From the plugin root: `npm test` and `npm run docs:check`. `npm test` includes:

- `tests/settings-connections.php`;
- `tests/settings-credential-broker.php`;
- `tests/settings-credential-rotation.php`;
- `tests/settings-service-meta-schema.php`;
- `contract:settings-station`;
- `regression:settings-home`.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station Navigation](admin-station-navigation.md), [Station Tab Set](station-tab-set.md), and [Service Station](service-station.md).
