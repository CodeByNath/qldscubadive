# Settings foundation bundle — Settings Station + Connections/Security + configurable Meta schema

Status: AWAITING REVIEWER REVIEW
Phase: Post-Phase-6 Settings foundation
Actor: Reviewer

## Owner direction

Phase 6 is closed. The next priority is **Settings Station and platform connections before Service importer implementation**.

This package is deliberately larger: complete the Settings foundation, Connections/Security seam, configurable Meta schema foundation, roadmap correction, tests and docs in one topic branch where authority is clear.

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md`
4. `skills/qsd-platform-architecture/SKILL.md` + its domain ownership / identity references
5. `docs/architecture/StationDrawerLifecycleContract-v1.md`
6. relevant Station Manager/Admin Station Code Maps and source
7. Service Code Map/source only where Service Meta ownership is planned

The Google Drive handover is continuity context only, but preserves the Owner sequencing decision above.

## Locked ownership

- Settings Station = platform/business configuration authority, not Service domain authority.
- Connections/Security = Settings-owned Tool for Rezdy, Stripe and future providers.
- Provider secrets remain server-side. Domain Stations/tools consume controlled capabilities; they never read credential storage directly.
- Rezdy is the first Connector implementation, **not** platform architecture.
- Service Meta **schema/configuration** may be Settings-owned; Service Meta **values** remain Service-owned and must flow through Service drafts/settle/projection/lifecycle.
- Existing `QSDS` / `QSDC` identity survives unchanged.
- Do not mint Platform IDs for credentials, rotating request keys, field definitions, repeater rows or gallery entries in this package. Use stable internal identity where reorder/rename requires it; never use position, sort order, slug or label as durable identity.

## Work package

### A. Correct roadmap/current-state sequencing
Update `docs/roadmap.md` so post-Phase-6 work begins with Settings foundation → Connections/Security → Rezdy Connector seam → Service importer, before deeper imported-Service work. Preserve Service Details/public API/deployment as later work.

### B. Settings Station foundation
Create the real peer Settings Station/navigation destination/surface according to current Station Manager registration rules. Do not confuse it with Service Home's existing `ServiceSettingsLane` (Create Service/Create Category only).

The Settings surface should be a configuration home capable of hosting Settings-owned Tools; do not create a fake domain record merely to satisfy Station lifecycle grammar.

### C. Connections/Security Tool foundation
Establish the provider-neutral connection boundary and server-side storage/API contract for provider configuration/secrets.

Required properties:
- provider-neutral model capable of Rezdy, Stripe and future providers;
- secret values never projected back to frontend after save;
- frontend receives only safe connection state/metadata;
- consumers use a controlled capability/connector boundary rather than credential storage;
- no hard-coded Rezdy architecture;
- no production credentials or deployment.

If the proposed short-lived single-use scoped rotating request-key mechanism requires a new security architecture decision beyond clear repository authority, implement only the provider-neutral capability seam and record a **BLOCKED — DECISION REQUIRED** subsection for that mechanism rather than inventing cryptography/security semantics.

### D. Configurable Meta schema foundation
Plan and, where architecture is clear, implement a Settings-owned schema manager that can define/remove/reorder configurable Service metadata fields.

Initial field capability must be designed for at least:
- text / textarea or rich content as repository conventions allow;
- number;
- boolean;
- select/options;
- image;
- image gallery;
- repeater / structured group.

Rules:
- field definition has stable internal identity independent of label/order;
- deleting a schema field must not silently orphan or destroy Service data without an explicit policy;
- repeater/gallery children must not depend on array position for identity when edits/reorder need stable matching;
- schema definition/config belongs to Settings; actual Service values belong to Service;
- no field type may bypass Service validation/draft/settle/public-projection ownership;
- do not hard-code scuba fields as the schema engine itself.

Document the future Service Meta module integration contract even if full Service Meta editor/value persistence is too large for this package.

## Hard Rezdy checkpoint

**Do not design or implement the Service importer / Rezdy field mapping from scratch.**
When the work reaches concrete Rezdy import/mapping architecture, stop and record:
`OWNER INPUT REQUIRED — obtain the Owner's pre-built Rezdy importer system before importer design continues.`

Do not substitute assumptions for that system.

## Validation / evidence

Add focused contracts/regressions for new Settings registration, ownership boundaries, secret non-projection, stable schema-field identity, and any implemented CRUD behavior.

Run from `wp-content/plugins/qsd-platform/`:
- `npm test`
- `npm run docs:check`

Update/new Code Maps must match source.

## Hard exclusions

- No Service importer or Rezdy mapping implementation before Owner system review.
- No live Rezdy/Stripe calls or credentials.
- No production/staging deployment.
- No public API expansion unless strictly required for the Settings-owned admin contract.
- No new Platform ID family without a separate Owner/Reviewer architecture decision.
- No multi-supplier/aggregator architecture.
- No transfer of Service persistence authority into Settings/Admin/Station Manager.

## Builder execution model

Use one topic branch and complete all non-blocked items above before handoff. Stop early only for a genuine architecture/security decision gate or the Rezdy Owner-input checkpoint.

## Handoff

Push one exact topic SHA and update this file to `AWAITING REVIEWER REVIEW` with:
- architecture/ownership decisions actually implemented;
- changed files;
- Settings registration evidence;
- Connections/Security storage/projection evidence;
- Meta schema identity/types/CRUD and deletion-policy evidence;
- roadmap/Code Map changes;
- tests/checks;
- unresolved gates;
- confirmation that Rezdy importer/mapping was not implemented without Owner input.

## Builder handoff

**Candidate:** `settings-foundation/settings-connections-meta` @ `17f47b563ee39726003197806bb539d7736e1365` — one commit on accepted `main` `7b099dac5e54a180540dcde29cd7f1ea439614fd`.

### Active-file note

Two files were opened with `Status: BUILDER ACTION REQUIRED`: `settings-foundation.md` (8b1a92e) and this `settings-foundation-bundle.md` (80fa03c, later). Same scope. I treated this later bundle as the single active file, applied the stricter stop conditions from `settings-foundation.md` (identity audit before minting, no destructive removal without a recovery rule, no generic connector abstraction beyond what Rezdy proves), and did not edit `settings-foundation.md`. Reviewer to close or supersede it.

### Architecture/ownership decisions implemented

- **Settings Station** = configuration authority: a real peer (`src/Modules/Settings/`, `resources/ts/settings-station/`) registered through Station Manager (nav `settings` order 90, destination, data source, kit; Admin places the binding). It has no domain record, no lifecycle, no drawer, and no Platform ID family. The data source is an honest empty collection, not a fabricated record. Separate from Service Home's `ServiceSettingsLane` (unchanged).
- **Connections/Security** is provider-neutral. `ConnectionProviderDefinition` declares `text`/`select`/`secret` fields. `ConnectionStore` is the sole owner of the non-autoloaded `qsd_settings_connections` option. Secrets are accepted on PUT and never projected: secret fields return only `configured`. An empty secret keeps the stored value, `clear` removes it, and DELETE disconnects. `ConnectorCredentials` is the only server-side read path for Connectors. `ConnectionProviders` lists only Connectors with a real consumer (Rezdy). Stripe is not added because it has no consumer; adding it means one more definition, with no store/route change.
- **Rezdy Connector seam:** environment (production/staging) + API key definition, plus `connectionState()` (configured, environment, base URL; never the key). No HTTP call, no mapping, no import.
- **Service Meta schema** (Settings owns definitions only):
  - Types: text, textarea, number, boolean, select, image, gallery, repeater. Sub-fields may be any non-repeater type, one level deep.
  - Identity: three-rung audit puts definitions at rung 2 (scoped child of the schema), so no Platform ID is minted. Server-minted `fld_` + 10 characters for fields and sub-fields (unique schema-wide), and `opt_…` for select options. Client ids are ignored. Identity never derives from label, slug or order; order is presentation only.
  - Non-destructive policy: no delete route; **retire/restore** keeps the definition and its id. Type is immutable. Existing option/sub-field ids cannot be dropped (422) but can be relabelled and reordered. Reorder must list every field id exactly once.
  - No Service storage is touched. The value-side integration is documented in `docs/architecture/service-meta-schema-contract.md` (Draft): values keyed by field id, select values stored as option ids, and repeater rows/gallery entries carrying stable row ids minted at the Service write boundary. Values flow through Service draft → settle → projection, and `required` is enforced by Service once values exist.
- **Access:** all routes use `qsd/v1/admin/settings/*` and are gated by `PlatformAccess::CAP` (`requireAdmin`). There is no public API expansion.

### Changed files (43)

- Backend (new): `src/Modules/Settings/{SettingsModule.php, CLAUDE.md}`, `Connections/{ConnectionProviderDefinition, ConnectionProviders, ConnectionStore, ConnectorCredentials}.php`, `Connectors/RezdyConnector.php`, `ServiceMeta/{ServiceMetaSchema, ServiceMetaSchemaException}.php`, `Http/{SettingsConnectionsController, ServiceMetaSchemaController}.php`. Modified: `src/Core/Plugin.php` (wires `SettingsModule`).
- Frontend (new): `resources/ts/settings-station/{types, api, errorMessage, useSettingsConnections, useServiceMetaSchema, useSettingsHome, register, index}.ts`, `CLAUDE.md`, `presentation/{SettingsDeck, SettingsConnectionsLane, ServiceMetaSchemaLane, ServiceMetaFieldEditor}.tsx`.
- Frontend (modified): `modules/admin-station.ts` (`registerSettingsStation()` after Service, before Admin), `admin-station/register.ts` (Settings Home binding), `admin-station/shell/icons.tsx` (`SettingsIcon`), `admin-station/styles/admin-station.css` (`cz-settings-*` deck/lane rules; controls still painted only by `cz-tf-*`).
- Tests (new): `tests/settings-connections.php`, `tests/settings-service-meta-schema.php`, `scripts/settings-station-contract.ts`, `scripts/settings-home-regression.mjs`. Modified: `package.json` (`contract:settings-station`, `regression:settings-home`).
- Docs: new `docs/code-map/settings-station.md` (indexed under Configuration), new `docs/architecture/service-meta-schema-contract.md` (Draft). Updated: `docs/roadmap.md`, `docs/ai-index.md`, `docs/code-map/station-manager.md` (boot order), `admin-station-navigation.md`, `station-tab-set.md`, and `skills/qsd-platform-architecture/references/domain-ownership-map.md` (Settings rows). `agents/openai.yaml` is unchanged because it lists no owners and the vocabulary is unchanged.

### Settings registration evidence

`contract:settings-station` (33 checks) runs the real Service → Settings → Admin registration and finalize, then confirms:
- `settings` appears in header and menu after Services;
- `resolveDestination('settings')` → station `settings`;
- one presentation binding (`settings-deck` / `settings-home`) with no intents and no drawer, and the kit resolves to `SettingsDeck`;
- the source returns an empty collection, and `resolveDrawerTemplate('settings') === null`;
- default Home is still `services`.

Source checks: no Settings file imports a Service peer; presentation imports no endpoint module; `register.ts` is imported only by the entry and is not in the barrel; the secret projection type has no value slot; `ServiceSettingsLane` is unchanged; the Settings backend has no `PlatformIdentifier` use; the schema touches no Service storage.

### Connections/Security storage/projection evidence

`tests/settings-connections.php` (26 checks, real controller/store/capability/connector):
- every route is gated by `requireAdmin`, and users without `manage_qsd` are refused;
- Rezdy is the only provider and starts `not_configured`;
- after saving environment + API key, the save and list JSON never contain the secret, and the secret field has `configured:true` with no `value` key;
- the option is stored with autoload `no`;
- `ConnectorCredentials` returns the trimmed secret server-side, and `RezdyConnector::connectionState()` returns configured/staging/base URL without the key;
- an empty secret keeps the stored key; `clear` → `incomplete`;
- unknown provider → 404; secret-as-config, config-as-secret, out-of-options select value and unknown field → 422;
- disconnect removes everything.

`regression:settings-home` (29 checks, real `SettingsDeck` mounted):
- a typed secret is sent once on save and the input clears afterwards;
- the secret value never appears in rendered HTML, and a saved secret shows only a "Saved…" placeholder;
- re-saving sends no secret; "Remove saved value" sends `clear:["api_key"]`;
- Disconnect is armed with no request, Cancel sends nothing, and Confirm sends exactly one DELETE.

### Meta schema identity/types/CRUD and deletion-policy evidence

`tests/settings-service-meta-schema.php` (37 checks):
- all 8 types are created with distinct server-minted `fld_` ids, and a client id is ignored;
- options get distinct `opt_` ids, and sub-field ids are unique schema-wide;
- rename keeps the id, and duplicate labels stay distinct identities;
- reorder changes order only; reorders that are partial or repeat an id → 422;
- option relabel/reorder keeps ids, new options are minted, and dropping or forging an option → 422;
- sub-field reorder keeps ids; dropping a sub-field, changing a sub-field type or nesting a repeater → 422;
- changing a field type → 422; unsupported type, empty label, a select without options, a repeater without sub-fields, or options on a non-select → 422;
- retire keeps the definition and id, an edit cannot change status, and restore returns the same id to active;
- unknown id → 404; there is no delete operation.

`regression:settings-home` Service Meta checks:
- Add field POSTs label/type/option labels with no client ids, and the row shows the server id;
- on edit the type is disabled and existing options offer no Remove; saving PUTs by id with existing option ids;
- move up POSTs `{ids:[…]}` and rows re-render in the new order with unchanged ids;
- Retire is armed with no request and Confirm POSTs retire once; the row reads Retired and stays listed; Restore POSTs restore;
- no delete request is ever sent.

### Roadmap/Code Map changes

- **Roadmap:** Phase 6 is marked done/accepted. A new "Next" section records Settings foundation → Connections/Security → Rezdy Connector seam → Service importer (blocked on Owner input) → Service Meta value module. Service Details, new Stations, the public read API and production deploy move to "Later". Descriptive Service details now arrive through Service Meta, not hard-coded Overview fields.
- **Code Maps:** see Changed files. All maps are under 600 words, and `docs:check` passes (45 Markdown files, 19 Code Maps).

### Tests/checks (from `wp-content/plugins/qsd-platform/`)

- `npm test`: exit 0 (typecheck; PHP tests including both new files; build; **24/24** JS contracts/regressions/snapshots; docs:check).
- `npm run docs:check`: passed.
- Focused runs passed: `php tests/settings-connections.php`, `php tests/settings-service-meta-schema.php`, `npx tsx scripts/settings-station-contract.ts`, `node scripts/settings-home-regression.mjs`, `npm run contract:admin-station-css`.
- Not performed: browser or real-WordPress runtime verification of the Settings screens or routes. Evidence is PHP with in-memory WordPress stubs plus happy-dom mounts.

### Unresolved gates

- **BLOCKED — DECISION REQUIRED: rotating request keys.** Short-lived, scoped, single-use rotating request keys need a security architecture decision (issuer, scope model, TTL, storage, replay protection). Only the provider-neutral `ConnectorCredentials` seam is built; Connectors read the stored long-lived credential server-side.
- **DECISION REQUIRED: secret encryption at rest.** Secrets are stored as plain values in a non-autoloaded option, the WordPress norm. No cryptography was invented.
- **DECISION REQUIRED: credential permission level.** Managing provider secrets uses the platform capability `manage_qsd`, the same as all admin routes. A stricter capability, e.g. `manage_options`, would be an Owner decision.
- **DECISION REQUIRED: Service Meta field purge.** Permanent removal of a definition and its stored Service values needs an approved Service-value recovery rule. Retire/restore is implemented instead.
- **Service Meta value module (next work, not started):** Service-side persistence, editor, publish-readiness enforcement and public projection, per the Draft contract.
- **OWNER INPUT REQUIRED — obtain the Owner's pre-built Rezdy importer system before importer design continues.**

### Confirmations

- The Rezdy importer, product/service mapping and import flow were **not** designed or implemented. `RezdyConnector` is configuration and connection state only, with no HTTP calls.
- No live Rezdy/Stripe calls or credentials; no new Platform ID family; no Service persistence moved into Settings/Admin/Station Manager; no public API expansion; no staging/production deployment; no Project History created.
