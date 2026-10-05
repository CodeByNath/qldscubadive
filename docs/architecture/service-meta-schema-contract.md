# Service Meta Schema Contract

**Status:** Draft — Settings foundation, pending Reviewer acceptance. The definition half is implemented; the Service value half is a forward contract only.

## Purpose

Administrators define configurable descriptive Service fields (for example max depth, certification level, photo galleries, itineraries) instead of each one being hard-coded into Service Overview. This document fixes who owns what before the Service-side module is built.

## Ownership split

| Concern | Owner |
| --- | --- |
| Field definitions: id, label, type, help, required, options, repeater sub-fields, order, retired state | Settings — `ServiceMetaSchema` |
| Per-Service values, validation against a definition, drafts, settle, lifecycle, public projection | Service Station |
| Coordination only | Station Manager |
| Screen placement only | Admin Station |

Settings never reads or writes Service storage. Service never writes the schema option; it reads definitions through a Settings-owned read capability when the Service Meta module is built.

## Identity

Run against the platform architecture skill's three rungs:

- **Field definition** — rung 2, a scoped child of the schema. Stable internal id `fld_` + 10 characters, minted server-side. Not a Platform ID: nothing outside the schema addresses a definition on its own.
- **Select option** — rung 2 inside its field, `opt_…`. A stored select value is the option id, never its label.
- **Repeater sub-field** — rung 2 inside its repeater, `fld_…`, unique schema-wide.
- **Repeater row / gallery entry** (Service values) — rung 2 inside a Service's value. Each row or image entry must carry a stable row id minted at the Service write boundary, so edits and reorders match by id, never array position. No Platform ID family is minted for rows or entries without a separate decision.

## Value shape (forward contract)

A Service stores values keyed by field id: `{ "<fld_id>": value }`.

- text / textarea → string; number → number; boolean → bool
- select → option id
- image → an attachment reference
- gallery → list of `{ row_id, attachment }`
- repeater → list of `{ row_id, values: { "<sub fld_id>": value } }`

Values flow through the Service draft → settle → projection path like Inclusions and FAQs: saving writes a draft, Publish settles it, and the public projection returns settled values of active definitions only.

## Removal and change policy

- A definition is **retired**, never deleted. Retired definitions keep their id; stored values stay intact and matchable; restoring the definition makes them visible again. A retired field is excluded from editing and projection but not purged.
- Type is immutable. Existing option and sub-field ids cannot be dropped; they can be relabelled and reordered.
- Permanent purge of a definition and its stored values needs an approved Service-value recovery rule (Owner/Reviewer decision).

## Required fields

`required` is declared by Settings and enforced by Service at its publish-readiness boundary once values exist. Until the Service Meta module is built, `required` has no effect.

## Not in scope

Service Meta value persistence, editors, publish-readiness enforcement, public projection, and Rezdy field mapping. Rezdy mapping into Service Meta waits for the Owner's pre-built Rezdy importer.

See the [Settings Station](../code-map/settings-station.md) Code Map.
