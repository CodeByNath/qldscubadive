# Lifecycle and Module-State System

The locked platform contract for station identity, module pills/notifications,
drawer handoff, footer actions, and travel is [Station and Drawer Lifecycle
Contract v1](../architecture/StationDrawerLifecycleContract-v1.md). This map
describes the current implementation boundary. Service and Category conform.

## Ownership

Each domain backend/controller owns canonical lifecycle transitions and persisted drafts. Its Station hook owns request-scoped loading, mutation state, and draft-preferred projections. Shared utilities derive status and notifications only; they never persist lifecycle state.

Station Manager has no lifecycle rules or records. Registering a source, kit, or drawer makes a capability resolvable but does not move lifecycle authority.

## Two layers of state

| Layer | Values | Stored in |
| --- | --- | --- |
| Record travel | `active`, `disabled`, `archived`, `trashed` | `platform_status` in the owner's meta |
| Disable mask | non-empty `previous_platform_status` while `disabled` | owner's meta |
| Module transition | `not-configured`, `pending`, `settled` | `module_status` per module |
| Drafts | one draft per module | owner's draft meta keys |

WordPress `post_status` is never written after creation. A raw unmasked `disabled` is Pending; only the explicit mask is Disabled.

## Shared mechanics and presentation

- [StationLifecycle.php](../../wp-content/plugins/qsd-platform/src/Modules/Admin/Support/StationLifecycle.php) is shared transition/readiness infrastructure (live/bin sets, restore, delete guards).
- [stationPrimitives.ts](../../wp-content/plugins/qsd-platform/resources/ts/hooks/stationPrimitives.ts) provides shared mutation loading/error wrappers and patch/result helpers.
- [moduleStatus.tsx](../../wp-content/plugins/qsd-platform/resources/ts/drawer-kit/utils/moduleStatus.tsx) derives completeness, the Service overview status, catalogue buckets, and status presentation.
- [moduleNotifications/](../../wp-content/plugins/qsd-platform/resources/ts/drawer-kit/utils/moduleNotifications/index.ts) contains the generic evaluator plus the Service and Category rule groups. Rules derive notes/readiness and render nothing. A new Station adds its own rule file.
- [CanonicalEntityFooter.tsx](../../wp-content/plugins/qsd-platform/resources/ts/drawer-kit/CanonicalEntityFooter.tsx) maps canonical states into the shared record-footer grammar.

## Domain state boundaries

- [useServiceStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/useServiceStation.ts) owns Service detail, module drafts, saves/reverts, settle/publish, and travel actions; [derive.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/derive.ts) holds pure projections.
- [useCategoryStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/hooks/useCategoryStation.ts) owns Category's Overview Save hand-off, draft-preferred projection, Publish, explicit Disable/Enable mask, and travel.

A complete Overview Save creates the persisted Pending record, preserves the mounted drawer during native-ID handoff, and leaves Publish to settle/activate that existing identity. Enable and Restore return to Pending, preserving data and drafts.

## Backend authority

Domain controllers apply the engine at their own REST boundaries: [ServiceController.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Http/ServiceController.php) and [AdminCategoriesController.php](../../wp-content/plugins/qsd-platform/src/Modules/Admin/Http/AdminCategoriesController.php).

## Known gaps

- Restore and Permanent delete exist in the backend, API client, and bin table schemas, but no Admin Station surface lists archived/trashed records yet.
- The `/status` route applies any valid target (`StationLifecycle::applyStatus`); strict per-action transitions are enforced by the UI only.
- Service Publish sends settle and activate as two requests.

## Validation

From the plugin root: `npm test`.

## Related Code Maps

[Station Manager](station-manager.md), [Drawer System](drawer-system.md), [Service Station](service-station.md), and [Categories](categories.md).
