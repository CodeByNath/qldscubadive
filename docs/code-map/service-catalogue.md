# Service Catalogue

Service Catalogue follows the locked [Station and Drawer Lifecycle Contract](../architecture/StationDrawerLifecycleContract-v1.md): Overview Save creates the persisted Pending Service, and Publish acts only on its returned ID.

## Purpose and ownership

Service Station owns the Service Catalogue: Service browsing, creation handoff, Service drawer intent, data projection, and presentation kit. Service posts, direct Categories, meta, and lifecycle remain Service-owned.

Admin Station is the presentation/control host, not the Catalogue owner. Admin's string-key presentation policy places the registered Service lower deck on the Services destination. Station Manager resolves the binding, data source, kit, intent, and drawer contract without owning their behavior.

## Registration and composition

[register.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/register.ts) registers:

- the Services navigation item and destination;
- `services` and `service-catalogue` data sources;
- the `service-lower-deck` template kit;
- the `service` drawer contract.

Admin Station's `service-lower-deck` surface binding carries the deck's own action intents: `view` (Service), `view-category` (opens the `category` drawer key from the same lane), `create-service`, and `create-category` (both open at the `'new'` recordId sentinel) — one surface dispatching to more than one registered drawer key.

The Catalogue is no longer a wall of its own. [ServiceLowerDeck.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceLowerDeck.tsx) is the bound kit: it selects a lane and hands the Catalogue the template-kit props unchanged, so filters, sorting, pagination, table, and drawer intent behave exactly as before inside the `Details` lane. `Connections` and `Settings` are Service's own lanes now: [ServiceConnectionsLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceConnectionsLane.tsx) renders a read-only, shared-list projection of every live Category, with an All / Connected (`assigned_count > 0`) / Unassigned (`=== 0`) filter over loaded rows, via [useServiceHomeConnections](../../wp-content/plugins/qsd-platform/resources/ts/service-station/surface/serviceHomeConnections.ts), View opening the mature `category` drawer by real numeric id; [ServiceSettingsLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceSettingsLane.tsx) hosts the shared `General | Tools | Security` Settings pattern for `services`; Service's own contribution under General is [ServiceCreateLaunchers.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceCreateLaunchers.tsx), Create Service and Create Category, both opening their mature drawers at the `'new'` recordId sentinel (see [Settings and Security](settings-station.md)). `Bin` ([ServiceBinLane.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceBinLane.tsx)) lists archived and trashed Services and Categories (name, Platform ID, Archived/Trash pill, one split action; All/Archived/Trash filter over loaded rows) via [serviceHomeBin.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/surface/serviceHomeBin.ts); it reloads when selected and calls the kit's `refetch` so `Details` reflects a restore or delete. Lane semantics come from the Admin-owned `StationTabSet`; Service Home reaches no other Station's presentation module.

[useServiceCatalogue.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/surface/useServiceCatalogue.ts) reads current Services for the table and archived Services for the overview count, projected through one adapter. [serviceCatalogueAdapter.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/surface/serviceCatalogueAdapter.ts) builds presentation rows, while [ServiceCatalogue.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/presentation/ServiceCatalogue.tsx) renders them and calls no endpoints. A row reads its name over its bare `QSDS` — the identity itself, unlabelled, blank until Publish assigns one — and search matches that ID beside name, description, slug, and Category.

[ServiceDrawerHost.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/surface/ServiceDrawerHost.tsx) adapts the Service-owned drawer composition to Station Manager's drawer contract, and resolves the stable `'new'` recordId sentinel to `service: null` — no fabricated ServiceItem — so the SAME mature composition opens on its ordinary Overview module with nothing to fetch. [useServiceStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/useServiceStation.ts) represents that pending state with its own local Overview draft; a complete Overview Save creates a persisted Pending Service record with its Overview draft and final-seeds detail before replacing the local `null` identity. Before that hand-off child modules remain visible but Edit-locked; afterward they save against the returned ID, while Publish settles and activates that existing record. Admin Station's generic drawer shell hosts the resolved content and never saves Service data.

## Data boundaries

- [ServiceController.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Http/ServiceController.php) owns Service REST reads and WordPress post/meta mutations.

The Category filter uses the Service's direct Category slug. In `Details`, archived rows contribute to the overview count only; archived and trashed Services (and Categories) are browsed and acted on in the `Bin` lane (see [Lifecycle](lifecycle-system.md#bin-travel-surface)).

## Validation

Run `npm test` and `npm run docs:check` from the plugin root (covers `contract:service-catalogue-projection`, `contract:station-tabset`, `contract:service-home-connections`, the Service/Category regressions, and the route baseline).

## Related Code Maps

[Service Station](service-station.md), [Service Connections](service-connections.md), [Admin Station Drawer](admin-station-drawer.md), and [Station Manager](station-manager.md).
