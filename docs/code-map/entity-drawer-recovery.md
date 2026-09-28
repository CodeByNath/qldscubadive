# Entity Drawer Compositions

## Architecture

Service and Category drawers are host-neutral domain compositions. Registration and hosting do not transfer their data or mutation authority.

```text
registered Station drawer adapter
              ↓
Station Manager contract resolution
              ↓
Admin Station generic drawer shell
              ↓
EntityDrawerHostBridge
              ↓
owning composition → owning hook/API/REST boundary
```

[StationSurfaceHost.tsx](../../wp-content/plugins/qsd-platform/resources/ts/station-manager/StationSurfaceHost.tsx) dispatches the opening record identity and registered drawer key. [AdminStationDrawer.tsx](../../wp-content/plugins/qsd-platform/resources/ts/admin-station/shell/drawer/AdminStationDrawer.tsx) resolves and mounts the owner adapter. `EntityDrawerHostBridge` carries only close, footer, close guard, and optional mutation-complete callbacks.

## Shared rendering layer

[drawer-kit/](../../wp-content/plugins/qsd-platform/resources/ts/drawer-kit/EntityDrawer.tsx) provides schema placements, Overview/Connections tabs, module notifications, inline editing, action footers, and lifecycle-footer presentation. The kit contains no entity persistence. Controllers coordinate state/actions without JSX; presentation calls no endpoints.

## Owned compositions

- [service-station/drawer/](../../wp-content/plugins/qsd-platform/resources/ts/service-station/drawer/ServiceDrawerContent.tsx) owns Service Overview/Included Features/Common Questions, lifecycle, dialogs, and guarded exit flows. [useServiceStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/useServiceStation.ts) is its write boundary and absorbs the `'new'` pending state: only Overview is editable until its complete Save creates the persisted Pending Service and hands off the returned identity without fabricating a ServiceItem; Publish later settles and activates it.
- `entity-drawers/category/` and `entity-drawers/schema/` hold the Category composition and schema. [useCategoryStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/hooks/useCategoryStation.ts) is its write boundary and absorbs the `'new'` pending state without a fabricated CategoryStationItem.
- [drawerChrome.ts](../../wp-content/plugins/qsd-platform/resources/ts/entity-drawers/shared/drawerChrome.ts) contains shared guarded-close, lifecycle-runner, auto-dismiss, and outside-click helpers.

Schema and editor ownership follows the entity: Service under `service-station/drawer/`, Category under `entity-drawers/`. A new Station keeps its composition under its own `<station>/drawer/`.

## Identity and bundle boundary

Service and Category use numeric native IDs plus an output-only Platform ID (`QSDS`/`QSDC`). The Manager and host pass identities unchanged; adapters reject incompatible shapes. Admin Station is the single JS host entry.

## Validation

From the plugin root: `npm test` (includes `regression:service-create`, `regression:service-create-handoff`, `regression:category-create`, and `contract:drawer-module-entry`).

## Related Code Maps

[Drawer System](drawer-system.md), [Admin Station Drawer](admin-station-drawer.md), [Lifecycle](lifecycle-system.md).
