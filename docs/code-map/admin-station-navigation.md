# Admin Station Navigation and Destinations

Station Manager coordinates navigation definitions and activation-key resolution. Admin Station renders the registered rows and stores the current selection; each Station registers its own navigation item and destination.

Root: `wp-content/plugins/qsd-platform/resources/ts/station-manager/registry/`

## Registration and boot

`navigation.ts` defines `StationNavItem` and accepts rows through `registerNavItems()`. Each row includes identity, label, Admin-owned icon capability, activation key, header/menu visibility, and order. Service registers `services` at 10 and Settings registers `settings` at 90. A new Station registers its own row; leave gaps in `order` for placement.

`destinations.ts` registers `StationDestination` records and resolves an activation key to:

```ts
{ id, stationId, surfaceId, placement, mode, conditions? }
```

Service's destination declares `surfaceId: 'catalog'`, `mode: 'table'`, `placement: 'body'` and `conditions.scope: 'current'`. Settings has no navigation row or destination: it is a tab inside a Station (see [Settings and Security](settings-station.md)). They are navigation declarations; current visible content is composed independently from presentation bindings.

Registration rejects duplicate navigation ids, destination ids, and identical destination projections. `finalizeStationRegistry()` locks registration, builds stable order-sorted header and menu arrays, and asserts that every navigation activation key names a registered destination. Public navigation and destination resolvers throw before finalization. `resolveDestination()` returns `null` for a null or unmapped activation.

## Selection flow

```text
header or slide-menu row
  → AdminStationLayout.handleSelect(item.id)
  → AdminStationContext.navigate(id)
  → resolveDestination(activeDestinationId)
  → AdminStationBody selects destination.stationId
  → presentation bindings for that station
```

With no selection or an unmapped key, the body uses the registered default home, `services`. Services renders the Service lower deck (Details, Connections, Settings, Bin).

There is no URL router in this chain. An activation key is not a route. Destination resolution and surface-binding resolution are separate axes: the former chooses a station context; the latter chooses ordered live presentation surfaces.

## Boundaries

- Registry-native station ids (currently `services` and `settings`) are not legacy app-registry identifiers.
- `StationConditions` describes what a station surface addresses. It is distinct from the runtime registry's `MountCondition`, which decides where the app mounts.
- `destinations.ts` has one sanctioned type-only dependency on `drawer-kit/schema/types` for `ShellMode`; Station Manager has no peer runtime imports.
- Record ids remain native `string | number` values wherever conditions or intents carry them.
- `register.ts` modules are imported only by the Admin Station entry, and no resolver runs at module scope.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station](admin-station.md), [Surface Binding](admin-station-surface-binding.md), and [Admin Station Cards](admin-station-cards.md).
