---
name: qsd-platform-architecture
description: Use before proposing, designing, or auditing any new QSD Platform feature, Station, module, field, or entity relationship (courses, trips, dive sites, equipment, bookings, pricing, anything). Classifies the new concept against the platform's identity-preserving composition law, checks whether it needs its own Platform ID family, maps ownership, and flags identity/ownership drift before implementation begins.
---

# QSD Platform Architecture

## The law

**The QSD Platform grows by identity-preserving composition.** An existing
Platform-identified atom keeps its own identity forever. When that atom is
combined with a new, independently-meaningful platform concept, the
*combination* gets its own new Platform ID — the original atom's identity
is never copied, replaced, or flattened into it. That new composition may
later serve as an atom inside a still-higher composition.

This applies to every Station, not only the ones that exist today.

## Before proposing anything, audit first

Do not design a new field, entity, Station, or relationship before
completing this audit, in order:

1. **Audit existing ownership.** Which file/class already owns identity,
   lifecycle, persistence/projection, pricing (when it exists), and
   presentation for the area this touches? Read
   `references/domain-ownership-map.md`. Never assume a new layer is
   needed until you've confirmed no existing owner already covers it.
2. **Identify what identities already exist and must survive.** List every
   Platform ID already touching this data (`QSDS`, `QSDC`, …). Every one of
   them must still resolve, unchanged, after your change.
3. **Classify the new concept** — see the three-rung model below.
4. **If it's a composition, is a new Platform ID family actually
   justified?** Only when some other layer will need to address it
   independently later. See `references/platform-id-families.md` for the
   closed vocabulary and how to extend it (one entry in
   `PlatformIdentifierPolicy`, then wire the owning Station — the engine
   itself never branches on domain storage).
5. **Preserve identity through every write/read/projection boundary.** A
   Platform ID present in storage must still be present after every
   sanitize/extract/settle/project step between storage and the consumer
   that needs it — including the public `qsd/v1` read routes the front end
   will use. Trace the chain end to end; do not assume a field "carries
   through" without checking each hop.
6. **Reject position/index/label as durable ownership.** Array position,
   `sort_order`, slugs, and human-readable labels are never identity. Match
   by Platform ID or stable internal id, always.
7. **Never flatten a higher-order composition back into its source
   identity.** A composition (a scheduled trip built from a Service, a
   package built from several Services) keeps its own ID alongside the
   atoms it composes — never merge the two into one.
8. **Distinguish identity drift from ownership drift.** Identity drift:
   the wrong ID is exposed, dropped, or conflated (e.g. two things sharing
   a source id treated as the same object). Ownership drift: a
   parent-owned rule is derived from a child's own field instead of the
   parent's own data. Both are violations; name which one you're looking at.
9. **Reuse existing Platform Identifier infrastructure.** Never invent a
   parallel reservation/repair/backfill mechanism when
   `PlatformIdentifierStation` (reserve → assign → ensure → markDeleted,
   plus `assignExistingBatch`) already covers the shape you need — extend
   its policy and write the owning Station's callbacks instead.
10. **Conform to the lifecycle contract.** A new Station follows
   `docs/architecture/StationDrawerLifecycleContract-v1.md`: Overview Save
   creates the Pending record and hands the returned ID to the mounted
   drawer; Publish settles and activates; Disable is an explicit mask;
   Restore returns to Pending. Identity is reserved at create, bound after
   the native insert, and tombstoned on permanent delete.

## Three-rung classification

1. **Attribute — no identity.** Pure metadata on an existing record.
   Nothing else will ever need to address it on its own.
2. **Scoped child — addressable only inside its parent.** It may have a
   stable id (and, if justified, a Platform ID), but only within its
   parent's scope (e.g. `(parent_id, child_id)`). It is never reused as its
   own unit elsewhere on the platform.
3. **Independent atom — own Platform ID family, independently
   meaningful, may participate in higher-order composition.** It can be
   referenced, composed, and reasoned about on its own, and may itself
   become one ingredient of a still-higher composition later.

Getting the rung wrong is itself the most common failure mode: minting an
ID family for something that will only ever be rung 1 or 2 is over-
identification; leaving something at rung 1 when another layer will need
to address it independently is under-identification. When unsure, check
`references/identity-composition-model.md` for the worked contrasts.

## Reject these patterns

- Replacing an upstream Platform ID with a downstream one.
- Using array position, `sort_order`, a slug, or a label as durable identity.
- A source-of-truth layer absorbing a consumer's own orchestration or
  contract behaviour (e.g. a Service learning booking rules).
- A variant/presentation layer standing in for a genuinely different
  composition concept it was never designed to own.
- Flattening a composition's own identity into the atom it composes, or
  vice versa.
- Suppressing or merging two independently-identified compositions merely
  because they share an underlying source id.
- Minting identity in a read/projection path — identity is written only
  at a create/settle/mutation boundary, never synthesized on read.
- Reading another Station's storage directly instead of its public
  contract (for Service pools: `ServicePools` and the
  `qsd_service_pool_references` filter).
- Building a new repair/migration/backfill tool when
  `PlatformIdentifierStation`'s existing mechanism already covers the shape.
- Inventing a new layer without first completing the ownership audit above.

## Two lessons already paid for — do not relearn them

These were learned the hard way in the platform this was extracted from.

- **Shared source identity does not make two independently identified
  compositions the same object.** Two compositions claiming the same
  underlying atom is normal, not a collision; never suppress one because
  they reference the same source id. (Two trips that both run the same
  Service are two trips.)
- **Parent-owned rules must not be derived from a child's own field.** If
  a boundary/limit/policy belongs to the parent, compute it from the
  parent's own data — never infer it from whichever child happens to be
  present. (A trip's capacity is the trip's own field, not the first
  booking's party size.)

## References

- `references/identity-composition-model.md` — the three-rung ladder in
  depth, with current QSD examples and illustrative scuba cases.
- `references/domain-ownership-map.md` — who owns identity, lifecycle,
  persistence/projection, and presentation today, and the template for a
  new Station.
- `references/platform-id-families.md` — the closed Platform ID vocabulary
  and how to extend it.
