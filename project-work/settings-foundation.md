# Settings foundation — configuration, Service Meta, Connections, Rezdy gate

Status: BUILDER ACTION REQUIRED
Phase: Settings foundation
Actor: Builder

## Owner direction

Settings Station is the next platform work area after Phase 6.

Build the foundation in one substantial package, but stop at the explicit Rezdy importer gate below.

## Authority

Read before editing:

1. `AGENTS.md`
2. `docs/ai-index.md`
3. `docs/roadmap.md`
4. `docs/code-map/000-README.md`
5. relevant Admin Station / Station Manager maps
6. `skills/qsd-platform-architecture/SKILL.md`
7. `docs/architecture/StationDrawerLifecycleContract-v1.md` if a lifecycle-managed Settings record/drawer is introduced
8. authoritative source discovered from those maps

Repository/source authority wins over the Google Drive handover.

## Architecture direction

### 1. Settings Station

Create/plan Settings as platform/business configuration authority.

Settings must not absorb Service-domain persistence or lifecycle authority.

### 2. Service Meta configuration

The Owner requires an admin-configurable Service Meta system so administrators can define and later remove configurable Service fields instead of hard-coding each descriptive field into Service Overview.

Required capability includes at least:
- ordinary fields;
- repeaters;
- image/gallery fields;
- admin add/remove control.

Ownership boundary:
- Settings owns **field definitions/configuration**;
- Service Station owns **per-Service values**, validation, lifecycle/drafts/settlement, and projections for those definitions;
- Station Manager coordinates only;
- Admin presentation does not acquire persistence authority.

Before minting any Platform ID or child identity for field definitions/repeater rows, run the architecture skill's three-rung identity audit. Do not use array position, labels, or sort order as durable identity.

Do not silently destroy stored Service values when a field definition is removed. Audit existing platform patterns and either preserve recoverability or stop for an Owner decision before destructive semantics are introduced.

### 3. Connections / Security Tool

Settings owns the user-facing Connections/Security Tool for provider credentials and connection configuration.

Provider secrets remain server-side. Domain Stations/tools consume controlled connector capabilities; they never read credential storage directly.

Plan the credential boundary for short-lived, scoped, single-use/rotating request keys. These security artifacts are not Platform IDs.

### 4. Rezdy Connector

Rezdy is the first Connector implementation, not platform architecture.

Build only the generic connector/security foundation and the Rezdy connection seam that can be established without importer-specific assumptions.

## OWNER CHECKPOINT — pre-built Rezdy importer

**Before designing or implementing Rezdy product/service mapping, importer transformation rules, or canonical Service import flow, STOP and ask the Owner for the existing pre-built Rezdy importing system.**

Do not reverse-engineer a replacement, guess its contract, or commit importer architecture before reviewing that Owner-provided system.

After it is supplied, audit how it maps into:
- canonical QSD Service identity (`QSDS`);
- Service Meta definitions/values;
- manual + imported Service convergence;
- provider-mapped options;
- any unresolved Service Option identity rung.

## Builder package

The Builder may complete, on one topic branch:

1. source/ownership audit for the Settings Station residence and registration path;
2. Settings Station foundation/shell consistent with current Station architecture;
3. configurable Service Meta definition model and admin management surface for the explicitly requested field/repeater/gallery capability, preserving the ownership split above;
4. Service-side value boundary needed to consume those definitions without hard-coded field ownership drift;
5. Connections/Security Tool foundation and secure server-side credential boundary;
6. Rezdy connector registration/configuration seam only up to the importer checkpoint;
7. focused contracts/regressions and affected Code Maps;
8. update `docs/roadmap.md` so Settings → Connections/Security → Rezdy Connector → Importer is the recorded sequence before deeper Service-domain work.

## Stop conditions

Stop rather than invent when:
- the Settings Station residence/identity/lifecycle boundary is not supported by repository authority;
- a field-definition/repeater identity family would need a new Platform ID decision;
- destructive removal semantics would erase stored Service data without an approved recovery rule;
- importer/mapping design needs the Owner's pre-built Rezdy importer;
- a generic credential/connector abstraction has no real current consumer beyond what Rezdy proves.

## Hard exclusions

- No production deployment.
- No aggregator/multi-supplier architecture.
- No replacement Rezdy importer before Owner supplies the pre-built system.
- No flattening imported records into provider identity; canonical QSD Service identity remains authoritative.
- No Service Details hard-coding that bypasses the configurable Service Meta plan.

## Validation / handoff

Run from `wp-content/plugins/qsd-platform/`:
- `npm test`
- `npm run docs:check`

Push one topic SHA, update this same file to `AWAITING REVIEWER REVIEW` with changed files, architecture decisions, tests, and any decision gates encountered, then stop.
