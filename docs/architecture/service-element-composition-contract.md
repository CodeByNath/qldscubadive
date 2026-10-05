# Service Element Composition Contract

**Status:** Draft. Pending Reviewer acceptance. It replaces the earlier flat Service Meta value contract, `{"<fld_id>": value}`, which is no longer authoritative. The definition side, the Service instance side, and the draft → settle path are implemented. The public projection is not implemented yet.

## Purpose

Administrators describe a Service with configurable Elements instead of hard-coded Overview fields. Examples are max depth, certification level, a dive profile group, an itinerary and a photo gallery. This contract fixes two things: which side owns each concern, and how every instance keeps its identity.

## Ownership

| Concern | Owner |
| --- | --- |
| Element **definitions**: what an Element is. Covers label, type, help, required, select options, container sub-fields, order and retired state. | Settings: `ServiceMetaSchema` (the "Service Meta" Tool) |
| Read access to definitions | Settings: `ServiceElementDefinitions`. It is read-only and is the only way Service sees definitions. |
| Element **instances**: which Element exists on which Service. Covers values, Group children, Repeater rows, gallery entries, order, detached state, drafts, settle and projection. | Service Station: `Support/ServiceElements`, `ServiceController`, `ServiceModules` |
| Coordination only | Station Manager |
| Screen placement only | Admin Station |

Settings never reads or writes Service storage. Service never writes the schema option.

## Identity

The platform architecture skill's audit was run against this cross-Station consumer path:

- **Owning Service.** The Service keeps its `QSDS` Platform ID unchanged. No Element replaces or flattens it.
- **Definition.** A definition is rung 2 inside the Settings schema. Its id is `fld_…`, and select options use `opt_…`. The definition id says *what* an Element is. It is never used as an instance id.
- **Instance.** An instance is rung 2 inside its Service, a parent-qualified child. Its durable address is (`QSDS…`, child id). Child ids are `el_…` for an Element at any depth, `row_…` for a Repeater row, and `ent_…` for a gallery entry. Each is 10 characters from the Platform ID alphabet. Ids are minted only by the Service write boundary and are unique within the owning Service.
- **No new Platform ID family.** Nothing outside the owning Service needs to address an instance on its own. A future consumer (the public projection, an importer) reaches instances through the Service. Revisit this only if a second parent must reference an instance directly.

The definition reference crosses Stations, from Service storage into the Settings schema. This is safe because definitions are never deleted, a definition's type is immutable, and option and sub-field ids can never be dropped.

## One composition law

Every level follows the same rules: top-level Element, Group child, Repeater row and gallery entry.

- A **Group** is a container Element that carries `children`, one child Element per sub-field definition.
- A **Repeater** is a container Element that carries `rows`. Each row carries its own `children`.
- A **gallery** carries `entries` of `{ id, status, attachment }`.
- A payload names existing ids only. A new node is sent **without** an id, and a client-coined id is refused.
- Matching is by id only, never by position, label, slug or sort order. Array order is presentation only.
- An instance keeps its `definition_id` for life.
- Within one slot (the top level, one Group, or one row), at most one active instance may exist per definition.

Stored shape, `qsd_service_elements` / `qsd_service_elements_draft` = `{ version: 1, elements: [...] }`:

```text
{ id: "el_…", definition_id: "fld_…", status: "active"|"detached",
  value?: scalar | option id | attachment id | null,
  entries?: [{ id: "ent_…", status, attachment }],
  children?: [Element],
  rows?: [{ id: "row_…", status, children: [Element] }] }
```

Settings currently allows one level of nesting: sub-fields are non-container types. The value model is recursive, so deeper nesting is a Settings change only.

## Removal

Removal is non-destructive:

- **Detaching a node.** An existing node that a payload omits, or sends as `detached`, is kept with its id and value. Sending it back `active` restores it. Re-adding a removed field in the editor revives the same instance.
- **Retired definitions.** A retired definition (or a missing one) freezes its instances. They are carried forward verbatim and cannot be edited or newly added. Restoring the definition makes the same instances editable again.
- **Purge.** Permanently purging a definition or instance waits for an approved recovery/purge rule.

## Lifecycle

Elements are the fourth Service module, `elements`:

1. A Save writes the draft and marks the module `pending`.
2. Publish/Settle copies the draft verbatim to canonical, so every child id survives.
3. Revert drops only the draft.
4. Activation resolves the module from canonical.
5. The module is complete when at least one top-level Element is active.

Elements never gate Publish.

## Routes

- `GET /admin/services/{id}/elements` returns settled instances, the draft, and the definitions they reference (retired ones flagged).
- `POST /admin/services/{id}/elements` saves the draft.
- Settle and revert reuse the module routes.

The admin detail projects `elements` and `drafts.elements`.

## Not in scope yet

- **Required fields.** `required` is declared but not yet enforced at publish readiness.
- **Public projection** of settled active instances.
- **Media.** A media-library picker, beyond attachment-id entry.
- **Rezdy mapping.** Mapping into Elements waits for the Owner's pre-built Rezdy importer.

See [Settings Station](../code-map/settings-station.md) and [Service Elements](../code-map/service-elements.md).
