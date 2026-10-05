# Service Elements

Service Elements are the Service-owned **instances** of the Element definitions that Settings owns. Each instance's address is the Service's `QSDS` plus its own Service-child id. The rules are in the [Service Element composition contract](../architecture/service-element-composition-contract.md).

## Backend

- [ServiceElements.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Support/ServiceElements.php) is the single value model.
  - It merges a full-collection payload onto the draft-preferred stored collection with one identity law. That law covers Element, Group child, Repeater row and gallery entry.
  - It mints `el_`/`row_`/`ent_` ids for new nodes only and refuses client-coined ids.
  - It matches by id only and carries omitted nodes forward as `detached`.
  - It freezes instances of retired definitions.
  - It validates values against the definition: number, yes/no, select option id, and attachment reference.
- [ServiceElementException.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Support/ServiceElementException.php) carries the 422 message.
- [ServiceController.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Http/ServiceController.php) routes `GET`/`POST /admin/services/{id}/elements`. It adds `elements` to the settle/revert module routes and projects `elements` and `drafts.elements` in detail.
- [ServiceModules.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Support/ServiceModules.php) settles the draft verbatim, so every child id survives, and resolves the module on activation.
- [ServiceSchema.php](../../wp-content/plugins/qsd-platform/src/Modules/Service/Support/ServiceSchema.php) owns `qsd_service_elements` and `qsd_service_elements_draft` and the `elements` module key.
- Definitions arrive only through Settings' read-only [ServiceElementDefinitions.php](../../wp-content/plugins/qsd-platform/src/Modules/Settings/ServiceMeta/ServiceElementDefinitions.php). `Core\Plugin` injects it into `ServiceModule`.

## Frontend

- `types.ts` and `api.ts` own `ServiceElement`, `ServiceElementDefinition`, `fetchServiceElements` and `updateServiceElements`. Definitions come from Service's own Element route, never the Settings peer.
- [useServiceStation.ts](../../wp-content/plugins/qsd-platform/resources/ts/service-station/useServiceStation.ts) holds draft-preferred `elements`, the definitions, `saveElements`/`revertElements`, and the module status and notes. Settle and Publish apply the returned `elements` and clear the draft.
- `derive.ts` contains `resolveElementsStatus`, `deriveElementLines` (read-view lines keyed by instance id) and `deriveElementsSummary`.
- The drawer module is `serviceElementsShell` in `drawer/schema/bindings/service.tsx`, placed fourth in the Service manifest and edited by [ServiceElementsEditor.tsx](../../wp-content/plugins/qsd-platform/resources/ts/service-station/drawer/editors/ServiceElementsEditor.tsx) inside the ordinary module editor.
  - New nodes are sent without ids.
  - Removing a saved node sends `detached`.
  - Restoring a node, or re-adding its field, revives the same instance.
  - Retired-definition instances are shown read-only.
  - Images are entered as media attachment ids.
- The module DNA is `elementsModule` in `drawer-kit/utils/moduleNotifications/service.ts`.

## Not yet

These items are deferred:

- required-field enforcement at Publish;
- the public projection;
- a media picker;
- Rezdy mapping, which waits for the Owner's importer.

## Validation

From the plugin root: `npm test`. It includes `tests/service-elements.php`, `tests/service-route-baseline.php`, `regression:service-elements` and `contract:settings-station`.

## Related Code Maps

[Service Station](service-station.md), [Settings Station](settings-station.md), [Lifecycle](lifecycle-system.md).
