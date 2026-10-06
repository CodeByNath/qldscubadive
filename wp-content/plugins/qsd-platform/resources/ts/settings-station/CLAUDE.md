# Settings — Frontend Peer

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`resources/ts/settings-station/` is the Settings peer: platform configuration panels presented inside a Station's `Settings → General | Tools | Security` tab. It is not a Station: it registers no navigation, destination, data source or deck. It contributes panels through the Station Manager station-settings registry, and has no drawer and no lifecycle-managed record.

- `types.ts` — zero-import contracts. A secret field projection has `configured` and no value slot.
- `api.ts` — the single implementation of the `admin/settings/*` endpoint calls.
- `useSettingsConnections.ts`, `useServiceMetaSchema.ts` — panel state and actions; presentation calls these, never `api.ts`. `encryptionAvailable: false` means this site cannot store keys securely and refuses secret saves; it is never presented as a setup task. `canManageSecrets: false` (not an administrator) means view-only key state; the server enforces it regardless.
- `presentation/SecurityApiKeysPanel.tsx` — Security → API Keys: secure-storage and access state, one card per provider, a write-only add/replace key flow, Remove key and Disconnect armed with `useInlineConfirm`, Test connection (the server-side validation run), and Rotate encryption key (administrators; no body, count-only result).
- `presentation/ServiceMetaSchemaLane.tsx` (+ `ServiceMetaFieldEditor.tsx`) — General → Service fields.
- `presentation/RezdyImporterTool.tsx` — Tools → Rezdy importer slot; the importer is Phase 3.
- `register.ts` — contributes those three panels to `services`. It is imported only by `resources/ts/modules/admin-station.ts` and never exported from `index.ts`.

## Boundaries

Never keep or render a saved secret: a key input exists only while adding or replacing, and is cleared after save. Never name a server file, an encryption key, a shell step or an operator task in the UI; QSD owns the encryption, and provider keys enter only through this write-only flow. Field definitions are addressed by their server-minted id, never label or position. Settings does not own Service values, imports no Service peer, and is never imported by one. Panels presented in several Stations keep one data owner.

Read [Settings and Security](../../../../../../docs/code-map/settings-station.md).

## Validation

From the plugin root: `npm test` (includes `contract:settings-station` and `regression:services-settings`) and `npm run docs:check`.
