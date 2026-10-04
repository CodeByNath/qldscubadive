# Service Connections

## Purpose

Maps Service relationships to their current composition and persistence owners. There is no platform-wide relation registry: Station Manager registers and resolves surfaces and drawers, but it does not model, store, or mutate domain connections.

## Current connections

- **Category ↔ Service.** Service assignment is Service-owned: the Service Overview draft carries `category_ids`, and Publish settles them onto the `qsd_service_category` taxonomy. The neutral Category drawer shows assigned Services as a read-only projection. Service Home's Connections lane reads the same authoritative Category list (`fetchAdminCategories`, which carries server-computed `assigned_count`) and presents every live Category, filterable as All / Connected / Unassigned in presentation state; it invents no second relationship model and performs no mutation. See [Categories](categories.md).

## Extension point for future Stations

A future Station that references Service pool items (Inclusions or FAQs) — for example a dive trip or course package that lists a Service's included features — must:

1. write any new pool items through `Service\Support\ServicePools`, never by touching `qsd_service_*` meta directly; and
2. report its references through the `qsd_service_pool_references` filter, so Service's non-blocking settle guard can warn when a settle would remove a referenced item:

```php
add_filter('qsd_service_pool_references', function (array $refs, int $serviceId, string $module) {
    // $refs[$poolItemId][] = 'Human-readable referrer label';
    return $refs;
}, 10, 3);
```

[ServiceController.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Http/ServiceController.php)'s `poolSettleWarnings()` applies the filter before settling; with no provider it reports nothing. Service never reads another Station's storage.

Owning Station drawer compositions render relationships. Station Manager carries only registered contracts and the opening record identity; Admin Station's drawer shell only hosts the resolved owner contract.

## Related Code Maps

[Service Station](service-station.md), [Categories](categories.md), [Drawer System](drawer-system.md).
