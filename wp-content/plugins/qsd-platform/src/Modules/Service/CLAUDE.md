# Service Backend Boundary

Global policy is defined by [AGENTS.md](../../../../../../AGENTS.md).

## Ownership and entry points

This module owns `qsd_service` lifecycle, Service meta/drafts, category relationships written by its handlers, and Service inclusion/FAQ pools.

- `ServiceModule.php` — module wiring.
- `Http/ServiceController.php` — Service routes and validation/orchestration,
  including authenticated Platform-ID read delegation to numeric detail.
- `Support/ServiceSchema.php` — Service keys, module vocabulary, sanitization, and route arguments.
- `Support/ServicePools.php` — the one public pool-write contract; a future Station that adds pool items uses it and reports references through the `qsd_service_pool_references` filter.
- `Core\Plugin` injects the shared `PlatformIdentifierStation`; Service owns
  the `qsd_platform_id` post-meta callbacks and `platform_id` projections while
  permanent reservation/binding/tombstones remain Platform-owned.

## Boundaries

There is no pass-through repository; WordPress post/meta persistence remains cohesive here. `qsd_service` is private to WordPress (no public URL, archive, search, or `/wp/v2` route); all reads go through `qsd/v1`. Core registrars own centralized post-type/taxonomy declaration. Shared lifecycle infrastructure remains in `Admin/Support`. Nothing outside this module imports `ServiceController` internals.

The Service frontend peer — contracts, state, presentation, and the Service drawer/editors/schema — lives at `resources/ts/service-station/`.
Backend `platform_id` is mapped by the Service endpoint boundary to application
`platformId`; it is output-only and never belongs in a writable payload.

Read [Service Station](../../../../../../docs/code-map/service-station.md) and [Service Catalogue](../../../../../../docs/code-map/service-catalogue.md).

## Validation

From the plugin root: `npm test` (includes `tests/service-lifecycle-mask.php`, `tests/service-route-baseline.php`, and the Service regressions) and `npm run docs:check`.
