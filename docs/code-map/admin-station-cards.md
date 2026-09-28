# Admin Station Presentation Tools

Admin Station owns the reusable presentation tools rendered inside its host: the generic card-wall kit, Service Category carousel, status disclosure, metrics, split actions, and section shell. Domain Stations own their domain-specific sources and kits and may consume these Admin capabilities.

Root: `wp-content/plugins/qsd-platform/resources/ts/admin-station/presentation/`

## Admin-owned capabilities

- `category-groups/types.ts` defines `CategoryGroupCardItem` with native identity, display copy, optional status/notifications, metrics, and action descriptors.
- `CategoryGroupCard.tsx` and `CategoryGroupCardGrid.tsx` are pure presentation and collection-state components.
- `CategoryGroupCardsKit.tsx` adapts Station Manager's generic template-kit contract to that grid. It is registered as `category-group-cards` and is currently unbound — the ready-made card wall for a future Station (the `category-groups/` directory name is historical; the kit is entity-neutral).
- `ServiceCategoryCarousel.tsx` is registered as `service-category-carousel`; it and the `service-categories` source are currently unbound.
- `StationStatusPill.tsx` adapts shared module status/notification UI without defining domain status rules. `StationMetricBlock.tsx` and `StationSplitAction.tsx` provide repeated presentation patterns.
- `StationPresentationShell.tsx` renders the ordered presentation bindings for one station and delegates each live surface to Station Manager.

These components fetch and save nothing. Kits receive `{ items, loading, error, onIntent }` and emit native record ids plus action ids.

## Current live presentations

| Wall | Owning source / kit | Identity |
| --- | --- | --- |
| Service lower deck on Services | Service source + Service `service-lower-deck` kit | numeric Service id |

Service cards and Service Categories are registered but unbound.

## Peer presentation

`service-station/presentation/ServiceCatalogue.tsx` owns the searchable, filterable, paginated Service table and its Service projection. It consumes Admin status and icon capabilities but retains Service semantics.

Service and Category adapters preserve their native ids; no presentation adapter substitutes a display key or converts identity. `station-manager/useRetainedCollection.ts` keeps the last successful collection visible during wall refetches. A drawer save invokes only the refetch handle supplied by the opening wall.

## Styling

Admin presentation styles live in `admin-station/styles/admin-station.css` and its responsive companion. Shared drawer-kit component styles live in `resources/css/modules/drawer-kit.css`.

## Related Code Maps

[Station Manager](station-manager.md), [Admin Station](admin-station.md), [Surface Binding](admin-station-surface-binding.md), [Drawer](admin-station-drawer.md), and [Service Catalogue](service-catalogue.md).
