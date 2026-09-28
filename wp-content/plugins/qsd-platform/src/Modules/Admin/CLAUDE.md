# Admin Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

`AdminModule.php` wires the authenticated admin REST controllers. This module hosts no frontend surface — the admin frontend is the Admin Station. Controllers under `Http/` own Category and overview route validation/orchestration. `Support/StationLifecycle.php` (the shared lifecycle engine) and `Support/CategoryMeta.php` (Category storage, readiness, and Platform-ID term meta) are the shared support.

`Core\Plugin` injects the shared backend `PlatformIdentifierStation` through
`AdminModule` into `AdminCategoriesController`. Category retains both term
creation flows and owns the atomic `qsd_platform_id` term-meta callback and all
`platform_id` projections; the Station owns only permanent `QSDC` reservation,
binding, lookup, conflicts, and tombstones. Platform identity is output-only.
The Category Platform-ID GET resolves through that Station and reuses the
native-term authoritative projection; numeric identity remains unchanged.

## Boundaries

This module owns Categories and the shared lifecycle engine. It does not own Services (`Modules/Service`). Do not add `qsd_service` behaviour here or move frontend station/drawer ownership here. The shared admin capability is owned by `Core\PlatformAccess::CAP`.

Read [Categories](../../../../../../docs/code-map/categories.md), [Service Station](../../../../../../docs/code-map/service-station.md), and [Lifecycle](../../../../../../docs/code-map/lifecycle-system.md).

## Validation

From the plugin root: `npm test` (includes the Category PHP tests and `regression:category-create`) and `npm run docs:check`.
