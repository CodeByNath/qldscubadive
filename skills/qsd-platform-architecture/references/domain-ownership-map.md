# Domain Ownership Map

Who is allowed to decide what in the QSD Platform today. Before proposing a
new entity or Station, name the current owner in each column for the area
you are touching. If you cannot name an owner, resolving that is the first
task — not evidence that a new layer is needed.

## Identity infrastructure

`src/PlatformIdentifier/PlatformIdentifierStation.php` +
`PlatformIdentifierPolicy.php` (under `wp-content/plugins/qsd-platform/`)
mint, validate, reserve, bind, look up, and tombstone. They own **no**
native entity, lifecycle, validation, draft, projection, or relationship
data. The owning Station supplies scalar read/write callbacks; the engine
never branches on domain storage. Extending identity means adding one
entry to `PlatformIdentifierPolicy::PREFIXES` and wiring the owning
Station — never teaching the engine a domain shape.

`assignExistingBatch()` is the engine's one bounded backfill primitive. No
batch surface is wired today; if records ever need retroactive IDs, build
the smallest caller of that method — not a second mechanism.

## Lifecycle

`src/Modules/Admin/Support/StationLifecycle.php` is the shared transition
vocabulary (live and bin sets, restore, delete guards). Each Station's
controller applies it at its own REST boundary and owns its drafts and
`module_status`:

- Service — `src/Modules/Service/Http/ServiceController.php`,
  `Support/ServiceModules.php`, `Support/ServiceSchema.php`.
- Category — `src/Modules/Admin/Http/AdminCategoriesController.php`,
  `Support/CategoryMeta.php`.

Identity is reserved at create and bound after the native insert — never
on a read path.

## Persistence / projection

- Service: `qsd_service` posts + `qsd_service_*` meta and draft keys;
  Categories via the `qsd_service_category` taxonomy. Pools (Inclusions,
  FAQs) are written only through `Support/ServicePools.php`.
- Category: `qsd_service_category` terms + `qsd_category_meta` term meta.
- Both entity types are private to WordPress. Every read goes through a
  `qsd/v1` projection in the owning controller; a field a consumer needs
  must be named explicitly in that projection — existing in storage is not
  the same as reaching the consumer.

## Pricing

None yet. When pricing arrives it gets one owner (a Service module or its
own Station) and one engine; no other layer recomputes a price.

## Presentation

`resources/ts/<station>/` (types, `api.ts`, `use<Station>Station.ts`,
`drawer/`, `presentation/`, `register.ts`) and the Category composition in
`resources/ts/entity-drawers/`. Presentation never owns identity or
business rules: it mirrors backend-computed values and reads
draft-preferred projections. Notification rules
(`drawer-kit/utils/moduleNotifications/`) derive state only.

## Template for a new Station

| Column | New Station must name |
| --- | --- |
| Identity | its `PlatformIdentifierPolicy` entry and its controller's reserve/assign/tombstone calls |
| Lifecycle | its controller applying `StationLifecycle`, its modules and draft keys |
| Persistence / projection | its storage (post type, taxonomy, or option) and every `qsd/v1` projection |
| Pricing | the one owner, if any |
| Presentation | `resources/ts/<station>/` and its notification rule file |

Then follow the "Adding a Station" steps in `docs/ai-index.md`.
