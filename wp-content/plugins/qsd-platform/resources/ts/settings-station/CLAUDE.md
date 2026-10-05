# Settings Station — Frontend Peer

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`resources/ts/settings-station/` is the Settings peer: platform configuration Tools presented on Settings Home. It registers its own navigation, destination, data source, and kit with Station Manager; Admin Station places the binding. It has no drawer and no lifecycle-managed record.

- `types.ts` — zero-import contracts. A secret field projection has `configured` and no value slot.
- `api.ts` — the single implementation of the `admin/settings/*` endpoint calls.
- `useSettingsConnections.ts`, `useServiceMetaSchema.ts` — Tool state and actions; presentation calls these, never `api.ts`. `encryptionAvailable: false` means the server has no credential key and refuses secret saves; the lane says so. `canManageSecrets: false` (not an administrator) makes secret inputs read-only and hides Disconnect; the server enforces it regardless.
- `useSettingsHome.ts` — the honest empty data source for the deck binding.
- `presentation/SettingsDeck.tsx` — composition on the shared `StationTabSet`: Connections & Security, Service Meta.
- `register.ts` — imported only by `resources/ts/modules/admin-station.ts`; never exported from `index.ts`.

## Boundaries

Never keep or render a saved secret: secret inputs are write-only and cleared after save. Field definitions are addressed by their server-minted id, never label or position. Settings does not own Service values and imports no Service peer. This peer is unrelated to Service Home's `Settings` lane.

Read [Settings Station](../../../../../../docs/code-map/settings-station.md).

## Validation

From the plugin root: `npm test` (includes `contract:settings-station` and `regression:settings-home`) and `npm run docs:check`.
