# Identity Composition Model

## The three rungs, in depth

### Rung 1 — Attribute (no identity)

Pure metadata riding an existing identified record. No Platform ID, no
child id, no reservation. Nothing downstream ever needs to address it
independently — it is read as part of reading its owner.

**Current examples:** a Service's excerpt and description; a Category's
description. **Illustrative scuba cases:** a course's duration, maximum
depth, minimum age, or certification level printed on the card — all
attributes of the Service (via a Service module), not entities.

### Rung 2 — Scoped child (addressable only inside its parent)

Has a stable id and can be addressed — but only within its parent's scope,
and only ever read or written alongside that parent. Never composed into a
different parent, never listed platform-wide except through its parent.

**Current examples:** a Service's Inclusion and FAQ pool items — stable
string ids, owned and written only by their Service, pruned with it.
**Illustrative scuba case:** the individual line items of one booking's
gear hire.

A rung-2 child gets a Platform ID only when something must address it by
Platform ID (and then it is parent-qualified: `(parent_id, child_id)`).

### Rung 3 — Independent atom (own Platform ID family)

Addressable on its own, composable by multiple different higher-order
parents, and reasoned about independently of any one consumer. Being
composed by a higher layer never requires that layer to know how the atom
itself was assembled.

**Current examples:** Service (`QSDS`) and Category (`QSDC`). A Service is
referenced by its Categories' assigned-services projection today and will
be referenced by the public front end and future Stations.

**Illustrative composition, one layer up:** a scheduled dive trip or
course session that runs a Service on a date with a boat, a site, and
capacity. It composes the Service (keeping `QSDS` untouched) and gets its
own family because bookings, staff rosters, and the public site must each
address "this specific session" independently. Two coexisting identities,
neither replacing the other.

## How to tell rungs 2 and 3 apart when it's not obvious

Ask: **if a second, unrelated parent needed to reference this same thing,
could it, using the same identity, without going through the first
parent?** If yes, it's rung 3. If the concept only ever makes sense inside
its one parent's record, it's rung 2 — minting a family anyway produces an
identity nobody ever looks up independently, which is pure overhead.

## Deliberate non-identity boundaries

Some things look as though they deserve rung 3 but are intentionally
capped lower. Do not use them as precedent for minting a family without
running the test above.

- A higher layer may stay blind to how a lower atom was assembled: a
  booking references a session's Platform ID and never needs the Service's
  pool item ids to be meaningful.
- An internal role key (e.g. `'default'`, `'primary'`) is a legitimate
  matching key inside one record, but must never be exposed as a Platform
  ID once a real identity exists.

## Common misclassification traps

- **Treating rung 3 as rung 1** — assuming two things are "the same"
  because they share an underlying source id (two sessions of the same
  course are two sessions).
- **Leaving an identity out of one projection** — the ID exists in storage
  and in one route's output, but a second projection (a list route, a
  public route) never names it. Check every hop.
- **Promoting something to rung 3 that only ever needs rung 2** — watch for
  a field that feels like it "deserves" a Platform ID out of symmetry with
  a sibling, not because anything needs to address it independently.
