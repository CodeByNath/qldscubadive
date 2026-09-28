# QSD Platform AI Index

The QSD Platform is the Queensland Scuba Diving business platform. WordPress is its runtime and storage host only; the platform provides the domain Stations, permanent Platform IDs, the lifecycle engine, the `qsd/v1` API, and the Admin Station at `/station/`. The public website is a separate front end that reads the API.

## Read order

1. [AGENTS.md](../AGENTS.md)
2. `docs/ai-index.md` (this index)
3. The primary relevant [Code Map](code-map/000-README.md)
4. Authoritative source and stable `SECTION:` markers
5. Related Code Maps only when the source crosses a boundary
6. Relevant [Project History](project-history/000-README.md) only when needed

For current open work and standing decisions, read the [Roadmap and handover](roadmap.md).

## Peer Station model

- [Station Manager](code-map/station-manager.md) is coordinator-only. It owns registration contracts/resolvers, ordering and availability coordination, boot/finalize, generic surface composition, native record-identity transport, and retained-collection infrastructure. It owns no presentation primitive, domain logic, persistence, lifecycle rule, or drawer editor.
- [Service Station](code-map/service-station.md) is the Service peer and sole authority for Service data, IDs, lifecycle, validation, saves, catalogue presentation, and drawer composition.
- [Categories](code-map/categories.md) is the Category domain, hosted by Admin Station; it owns Category identity, Overview draft, and lifecycle.
- [Admin Station](code-map/admin-station.md) is a presentation/control Station and the thin frontend host. It owns shell chrome, icons, presentation tools, the generic drawer shell, and string-key presentation policy. Its drawer hosts the owning Station's registered contract; it never saves domain data.

Placement does not transfer authority. Peers register their own capabilities; Admin decides placement, order, conditions, kit selection, and the default Home through Station Manager. Peer imports of Admin presentation modules are legal capability consumption. Peer-to-peer domain consumption uses public barrels.

Each Station has sibling surfaces: **Station Home** for reading, browsing, monitoring, and showcase; and one first-level **Station Drawer** for editing. A drawer may use tabs but never nests another drawer. Closing returns to the same Home state.

The locked cross-Station lifecycle and drawer contract is
[Station and Drawer Lifecycle Contract v1](architecture/StationDrawerLifecycleContract-v1.md).
Read it before creating or editing a Station, module, drawer, or record footer.
Service and Category conform; every new Station must conform.

## Adding a Station

Run the [platform architecture skill](../skills/qsd-platform-architecture/SKILL.md) audit first.


A new domain (for example courses, dive trips, equipment) follows Service:

- **Backend:** `src/Modules/<Station>/` with a Schema (keys, module vocabulary, REST args), a Controller applying `StationLifecycle`, per-module drafts, and a new prefix in `PlatformIdentifierPolicy`; wire it in `Core\Plugin`.
- **Frontend:** `resources/ts/<station>/` with `types`, `api`, `use<Station>Station`, `drawer/`, `presentation/`, and `register.ts`; its notification rules in `drawer-kit/utils/moduleNotifications/`; one registration call in `modules/admin-station.ts`; placement in `admin-station/register.ts`.
- **Contracts:** extend `drawer-module-entry`, add a mounted create/handoff regression, and a Code Map.

## Boot contract

The Admin Station entry synchronously registers Service, Admin capabilities, and Admin presentation policy; finalizes Station Manager; then registers the mounted app. Peer `register.ts` modules are entry-only. No resolver runs at module scope or before successful finalization. See [Station Manager](code-map/station-manager.md).

## Capability vocabulary and lifecycle

- **Tool** — a user-facing operational system.
- **Skill** — a reusable deterministic operation.
- **AI capability** — a reasoning-backed operation.
- **Connector** — an integration boundary.

Capability lifecycle is **registered** with the platform → **available** to a Station → **activated** for an owning entity. Activation records are stored by the owning Station, never in generic shared business storage. Only the registration/finalize system has current consumers; the rest are reserved seams. Do not build them before a real consumer exists.

## Shared and presentation boundaries

- [Platform Identifier Station](code-map/platform-identifier-station.md) owns permanent Platform identity policy, reservation, binding, lookup, and tombstones. Domain Stations retain their native records and every domain action.
- `resources/ts/drawer-kit/` owns generic rendering and interaction contracts, not entity authority.
- `resources/ts/entity-drawers/` holds the host-neutral Category composition and shared drawer chrome.
- Admin-owned cards, grids, status primitives, icons, and generic drawer shell remain under `resources/ts/admin-station/`.
- Source code is authoritative when documentation conflicts.

## Documentation and validation

Code Maps describe current ownership and entry points. Architecture documents preserve stable constraints. Project History records completed milestones and is immutable. Local `CLAUDE.md` files contain local boundaries only.

From `wp-content/plugins/qsd-platform/`: `npm test` (typecheck, PHP tests, build, JS contracts and regressions) and `npm run docs:check`.
