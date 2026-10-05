# Settings foundation bundle — Settings Station + Connections/Security + configurable Meta schema

Status: BUILDER ACTION REQUIRED
Phase: Post-Phase-6 Settings foundation
Actor: Builder

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
