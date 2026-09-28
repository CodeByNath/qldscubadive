# QSD Platform — Architecture Standards v1

**Status:** Current platform standard
**Scope:** Stable backend, frontend, ownership, contract, and runtime constraints
**Working standard:** [AGENTS.md](../../AGENTS.md)
**Current source navigation:** [Code Map index](../code-map/000-README.md)

## 1. Authority before pattern

QSD is a relational, data-driven platform. Business truth belongs to its owning entity or domain; consumers receive shaped data and do not become authorities because they display or edit it.

There is no mandatory one-size-fits-all repository/builder pipeline. Use the smallest cohesive path that preserves the real authority:

```text
WordPress entity/meta → domain controller/service → typed endpoint → station hook → Admin Station consumer
owning Station's storage → domain controller → public qsd/v1 read endpoint → separate public front end
```

A repository is appropriate when it owns meaningful querying, storage, migration, or projection behaviour. It must not be introduced merely to wrap a single WordPress call. Controllers may coordinate WordPress entity APIs when that is the established cohesive domain boundary.

## 2. Backend domains

Backend modules live under `src/Modules/<Domain>/` and register through their module boundary. `Core/Plugin.php` orchestrates module boot; it does not acquire domain business logic.

- Controllers own route registration, permissions, request validation, orchestration, and response contracts.
- Support classes own cohesive schemas, sanitization, readiness, lifecycle, or shared rules.
- Repositories own meaningful storage/query/migration authority where the domain has one.
- Services/builders own non-trivial projection or business assembly.
- Templates render mount surfaces and must not become query or business-rule authorities.

Route location does not determine domain ownership. Compatibility URLs may retain another domain's navigation context while handlers and persistence stay with the true owner.

## 3. Persistence and relationships

Use WordPress posts, terms, taxonomy relationships, metadata, and options according to the established subsystem authority. Do not introduce parallel storage or copy data merely for convenient UI access.

- Taxonomies suit shared classification, queryable relationships, and term enrichment.
- Entity meta suits entity-owned structured state.
- Options/repositories suit a Station's aggregate persistence when records are not WordPress entities.
- References across domains must write through the owning public contract (for Service pools, `ServicePools` and the `qsd_service_pool_references` filter).
- Follow the owning relationship; never infer membership by traversing another Station's rows.

Registration is not ownership: centralized post-type or taxonomy registrars may declare an entity while its domain module owns behaviour. A nested REST path is likewise not ownership.

## 4. REST and TypeScript contracts

Routes live under the existing `/qsd/v1/` namespace and are registered by controllers. Preserve capability checks, validation, response shapes, and compatibility paths.

Every consumed response and mutation payload must have an accurate TypeScript contract. Keep a contract with its owning frontend station or neutral API type module; do not duplicate shapes or re-export them from unrelated legacy barrels. `any`, inline response guesses, and identity coercion are contract failures.

## 5. Frontend ownership

Hooks, stations, and domain services own fetch/mutation state. Presentation components receive data and intents; they do not call endpoints, persist lifecycle, or duplicate business truth.

Screen placement and source ownership are separate:

- `resources/ts/drawer-kit/` owns generic schema rendering, editor chrome, status/notification presentation, actions, and host bridges.
- `resources/ts/entity-drawers/<entity>/` owns host-neutral entity drawer composition and entity-specific coordination.
- `resources/ts/admin-station/` owns Admin Station navigation, surfaces, registries, shell adapters, and its one drawer shell.
- `resources/ts/<station>/` owns each domain Station's frontend peer (types, API, state, presentation, drawer).

Category and Service compositions mount in the one Admin Station host; a composition is never forked into a reduced copy. Generic shells must not branch on entity; registries select entity adapters, and adapters preserve native record identity.

### Locked Station and Drawer lifecycle

[Station and Drawer Lifecycle Contract v1](StationDrawerLifecycleContract-v1.md)
is the current cross-Station rule for module entry, status pills,
notifications, drawer identity handoff, child-module locks, record footers,
and travel. A complete Overview Save creates the persisted Pending record for a
conforming new Station; the returned native ID is seeded and transferred into
the same mounted drawer; Publish settles and activates that existing record.
The contract distinguishes the unmasked Pending storage enum from the explicit
Disable mask and requires Enable/Restore to return to unmasked Pending. Service
and Category are the current conformance examples. Other Stations remain
pending until their Code Maps say otherwise; do not copy their divergent
creation or travel rules into a new Station.

## 6. Shared systems

Shared code requires at least two genuine consumers, the same semantic responsibility, stable common behaviour, and no domain-authority leakage. Visual similarity and anticipated reuse do not qualify.

Keep domain notification rules in the domain-organised `drawer-kit/utils/moduleNotifications/` modules behind their barrel. Keep cross-domain presentation status in `drawer-kit/utils/moduleStatus.tsx`. A shared renderer derives or displays state; it never persists it.

Do not duplicate the mature drawer kit, station lifecycle, typed transport, relation provider, status pill, notification panel, inline editor, or lifecycle footer systems to obtain a different appearance. Extend them without capability loss when semantics are genuinely shared; keep behaviour local otherwise.

## 7. Runtime and shell boundary

WordPress is the runtime and storage host. The theme is a passive document shell; the platform plugin owns the Admin Station UI, Atomic Engine styles, runtime configuration, state, REST behaviour, and operational systems. The public website is a separate front end that reads the API.

```text
/station/ rewrite route → plugin document template → login gate | access denied | mount → platform runtime → Stations
```

Runtime configuration flows through `window.QSDConfig`, written only on the `/station/` route; no platform asset loads anywhere else.

## 8. Change standard

Preserve capability, validation, authority, native identity, dependency direction, runtime safety, and public contracts together. Line count, fewer files, generic reuse, or delivery speed is never a sufficient reason to compromise them.

Before replacing UI or infrastructure, inventory established actions, states, guards, error handling, persistence, and downstream contracts. After ownership or paths move, update imports, contracts/tests, Code Maps, local instructions, links, and applicable generated output. Validate only what was actually run and report missing PHP or browser runtime honestly.
