# Station and Drawer Lifecycle Contract — v1

**Status:** Current platform contract — locked
**Scope:** Station identity, modules, drawer composition, drawer group presentation, focused-task detours, confirm/prompt dialogs, record footer actions, lifecycle travel, pills, notifications, and child-module availability
**Current authority:** This document, the owning Station source, and the current [Code Map](../code-map/000-README.md)

This is the platform rule for adding or changing a Station, module, drawer, or
drawer footer. It records the behaviour proven by Service and Service Category
(and, in the CompuZign platform this was extracted from, by Package Family,
Tier occupant, and Tier Add-on — those examples remain below where they
illustrate an optional pattern).

## 1. Core rule

The owning Station is the only authority for identity, persistence, module
drafts, validation, lifecycle transitions, and notification derivation. A
drawer is a mounted presentation of that Station. It may coordinate identity
handoff and render the footer, but it must not create a second lifecycle or call
an endpoint from presentation code.

For a new conforming record or occupant, a complete Overview Save is the
persistence boundary:

```text
local Overview → create persisted Pending record → returned-ID handoff
→ child edits/saves (where the Station has child modules)
→ Publish settles eligible pending modules and activates the existing record
```

Publish never creates the record. The returned ID is handed into the same
mounted drawer composition. Authoritative detail is seeded before that identity
transfer, so Overview, child modules, notification panels, and the record
footer remain mounted and interactive. A second render, callback, or Publish
click is never required to complete the handoff.

This is a locked AI/contributor rule: a new Station must either implement this
contract or be listed as **pending migration**. It must not introduce a
create-on-Publish path, a replacement drawer during identity transfer, a
presentation-owned endpoint orchestration layer, or a second status/notification
system.

## 2. Vocabulary: storage, module state, and presentation

Storage and presentation are deliberately different.

| Layer | Values | Meaning |
| --- | --- | --- |
| Record travel | `active`, `disabled`, `archived`, `trashed` | Operational values written by the owning Station/backend. |
| Module transition | `not-configured`, `pending`, `settled` | Whether a module has saved draft data or settled data. |
| Drawer resolver | `pending-dim`, `pending-full`, `active`, `disabled` | Five internal presentation keys (the two Pending keys share one label). |
| Pill label | Active, Pending, Disabled | The only drawer/module labels. Archived and Trashed are travel labels on bin/history surfaces only. |

`pending-dim` is reduced-opacity Pending for an empty, incomplete, or not-yet-
available module. Its notification explains the missing prerequisite or next
action. `pending-full` is full-opacity Pending for complete saved data waiting
for publication (or configured data on an unactivated/unmasked record). Its
notification explains that publication is waiting. `active` is settled,
configured, and active. `disabled` is the explicit Disable action's mask.

The raw storage enum `platform_status: 'disabled'` is **not** automatically the
Disabled pill. A newly persisted Service or Category is unmasked with a pending Overview; it presents as Pending dim/full
according to readiness. Service and Category use their documented mask
signal (`previous_platform_status`). Only
the explicit Disable action makes the record and every module Disabled. This
distinction prevents a never-published draft from being described as a
user-disabled record.

Every pill is backed by a notification panel when its module has guidance,
errors, or a lifecycle explanation. The pill says only Pending/Active/Disabled;
the panel says why.

## 3. New record and Overview Save

1. The `new` drawer sentinel resolves to `null` (or the Station's documented
   local pending identity), never a fabricated numeric/string record.
2. The drawer opens readable on Overview. The module shell, pill, notification,
   and Edit action are present. An incomplete Overview is `pending-dim`.
3. In Service, child modules are visible but Edit-locked while no Service ID
   exists. Their `pending-dim` notifications say to save Overview first. The
   lock is an availability guard, not a different shell. Category's Assigned
   Services module is a read-only relationship projection and has no child
   editor to unlock.
4. A complete Overview Save crosses the owning persistence boundary exactly
   once. The Station takes the returned record/occupant, seeds authoritative
   detail and module status, then hands the returned identity to the already
   mounted drawer.
   There is no full loading mask, remount, or notification unbinding.
5. The saved Overview is `pending-full` with a publication notification. It is
   not settled or active. Service child modules become editable: an empty child
   is `pending-dim` with its add-content notification; a valid saved child is
   `pending-full` and waits for Service publication.

Overview completeness is entity-owned. Service requires its title, category,
and description/content gate. Category requires its name; description is
optional, and saving an empty description is authoritative (settlement removes
the owned description rather than retaining stale text).

## 4. Child modules and false-success prevention

After a real Service ID exists, Inclusions and FAQs save immediately through
Service Station against that ID. Their editors remain open on invalid input:

- Inclusions reject blank labels.
- FAQs reject a blank question or blank answer.

A missing ID is an invalid persistence state, not a successful empty save. The
Station keeps a defensive thrown error even though the Service child lock makes
the state unreachable through the normal drawer. Editors close, clear input,
and show success only after an authoritative write resolves successfully.

Each module remains in one position and one shell. Edit replaces only that
module's readable body with the shared inline editor and Save/Cancel footer;
sibling modules, pills, notifications, and the record footer remain mounted.

## 5. Publish, Disable, Enable, and travel

| User action | Station operation | Result in the mounted drawer/surface |
| --- | --- | --- |
| Publish | Settle every eligible saved module, then activate the existing record. | Settled configured modules become Active/green; an empty or unconfigured child remains Pending dim with its guidance. No create call. |
| Disable | Write the explicit disable mask; do not settle or activate. | Record and all modules show Disabled. |
| Enable | Clear the explicit mask; do not create, settle, or activate. | Configured/pending data returns to its Pending full state; empty children return to Pending dim. Existing drafts/data are preserved. |
| Archive / Move to Trash | Owning Station travel operation (Archive/Trash may be offered by the record footer where legal). | The drawer closes through its guarded terminal path; the record and its pending/settled data remain recoverable according to Station rules. |
| Restore | Bin/archive travel-surface operation, not available inside the drawer. | Returns to the unmasked Pending re-entry state, preserving module data/drafts; it does not auto-activate. |
| Permanently delete | Legal only for a trashed record and guarded by the owning Station's dependency rules. | Removes the record; no drawer or module may fake a successful delete. |

For a local `new` drawer with no persisted ID, Move to Trash is simply discard/
close of local authoring state; it is not a status write against a nonexistent
record. Footer actions remain one shared record-footer grammar, with module
Save/Cancel taking over the footer while an inline editor is open.

## 6. Drawer ownership and footer boundary

The generic drawer host owns chrome, scroll/focus/close behaviour, record
identity transport, and one footer slot. The owning Station supplies the
composition, bindings, module rules, editors, validation, and lifecycle
handlers. Drawer Kit supplies neutral shells, pills, notification panels,
inline editor chrome, and footer rendering; it owns no records or endpoints.

The controller is a thin handoff/coordination layer. It may receive the
returned ID, preserve the mounted composition, select a tab, open a confirm
dialog, and publish the Station's footer intents. Endpoint orchestration stays
in the Station hook or its existing lifecycle authority. Presentation receives
data and handlers only.

## 7. Required contract for new Stations and edits

Before changing or adding a Station, an AI or contributor must read this
contract, the relevant Code Map, local ownership instructions, and the
authoritative source. The implementation must demonstrate:

- one Station-owned lifecycle and one native identity path;
- readable Overview entry with a pill and notification, including empty/new;
- a documented child lock only where no authoritative ID makes a write
  impossible;
- persistence-on-Overview-Save for the compliant Station, returned-ID handoff, and
  authoritative detail seeding before identity transfer;
- draft-preferred module data and explicit validation/error retention;
- Publish as settle/activate of an existing ID, never record creation;
- explicit Disable/Enable masking with Pending re-entry;
- travel and permanent-delete guards owned by the Station;
- one shared drawer/module/pill/notification/editor/footer system; and
- mounted regression coverage for identity continuity, notification continuity,
  input availability, footer actions, and the absence of a second click.

If the source does not yet meet one of these points, mark the Station and its
  Code Maps **pending migration** instead of copying conforming-entity claims.

## 8. Conformance and pending inventory

### Conforming now

- **Service:** `service-station.md` and `service-catalogue.md`; Service
  Overview Save creates the persisted Pending Service, preserves the mounted
  handoff, unlocks child saves, and Publish settles/activates the returned ID.
- **Service Category:** `categories.md`; Overview Save creates the persisted
  Pending Category, preserves the mounted handoff, keeps Assigned Services
  read-only, and Publish settles/activates that ID.
- **Shared drawer ownership:** `drawer-system.md` and
  `admin-station-drawer.md`; the host is generic and the Station is the write
  boundary.

### Known gaps (tracked, not exceptions)

- **Travel surfaces:** Restore and Permanent delete are implemented by both
  Stations' backends and API clients, and Service declares its bin tables,
  but no Admin Station surface lists archived or trashed records yet (§5
  places Restore on a bin/archive travel surface).
- **Transition enforcement:** the `/status` routes accept any valid target;
  strict per-action transitions are currently enforced by the drawer only.

Every new Station implements this contract from the start; there is no
pending-migration inventory in this repository.

## 9. Drawer group presentation: Tabs, Accordion, child navigation, and focused tasks

This section locks the additive drawer-composition primitives introduced for
the Tier occupant/Edition drawer: `drawer-kit/ui/drawerGroups.ts`,
`DrawerGroupTabs.tsx`, `DrawerGroupAccordion.tsx`, `ui/ChildChipStrip.tsx`,
`ui/useScrollHide.ts`, and `FocusedTaskShell.tsx`. They are optional to
adopt — most current drawers still render through `EntityDrawer.tsx`'s fixed
two-tab `DrawerTabs.tsx` bar, which stays the platform default and is
deliberately not configurable. Once a Station's drawer needs more than that
fixed Overview/Connections bar, it must use these primitives exactly as
documented here rather than a bespoke tab, accordion, child-nav, or detour
implementation.

**Group content.** A drawer body that needs more than the fixed two tabs is
one ordered array of `{ id, label, content }` groups, rendered through either
`DrawerGroupTabs` or `DrawerGroupAccordion` against the identical array — a
drawer must never fork its content between the two renderers. Both accept an
optional `trailing` slot for a view-toggle or other compact control that
neither renderer interprets or owns.

**Tabs/Accordion selection is per-instance, unpersisted view state**, not a
per-entity default and never a saved preference: it resets to Tabs on every
drawer mount. A Station may offer the toggle or omit it; it must not persist
the choice across sessions, records, or drawer remounts.

**`--cz-drawer-group-chrome-h`** is the contract between the active group
renderer and any nested sticky child navigation: Tabs publishes its own real,
measured tablist height; Accordion always publishes `0px` (it has no
persistent sticky chrome above an open panel). A nested `ChildChipStrip`
reads this variable and must not hardcode an offset of its own.

**Child navigation** for a group whose content further splits into child
records (Options → Editions today) is `ChildChipStrip` — a subordinate,
visibly smaller sibling of the group bar, never a second top-level tab
system. Its optional `trailing` slot holds exactly one fixed, non-chip
control (e.g. a Bin icon); `trailing` never participates in chip selection.
Scroll-hide on the strip is opt-in via a caller-supplied `scrollContainer`
and must key off whichever container the active group renderer actually
scrolls — Accordion mode disables hide/reveal (passes `null`) rather than
hiding against the wrong container.

**Focused tasks.** Any drawer detour that must fully replace a group's
content and suppress the surrounding drawer chrome — a bin, a wizard step, or
any other full-view task — is `FocusedTaskShell` (Back, task title, optional
task-state badge, one scrollable body, one footer). It carries no
dirty-state, confirm, or save/cancel opinion of its own; that behaviour
belongs to the caller. A focused task must be the ONE visible identity for
its concern: it replaces the child chip strip and its cards outright and
must never render alongside a second nav row or a duplicate entry point.
`InlineEditorShell` is the save/cancel/dirty-confirm specialisation of this
same shell and must remain the same DOM and behaviour it already has.

## 10. Chrome suppression while an editor or focused task is open

When any module editor, child-record editor, or focused task (e.g. the Bin)
is active, the surrounding group chrome (tablist, accordion triggers, group
borders) must be suppressed by toggling one class on the drawer's outer
wrapper (e.g. `cz-req-detail--editing`) and hiding it in CSS — never by a
conditional render swap that unmounts and remounts the group chrome. The
underlying group tree must stay mounted at the same tree position so an open
editor's own local state is never wiped by a remount the instant editing
starts. A drawer host may optionally hide its own header through a
`setHeaderHidden` bridge capability; that capability is optional and
additive, and every host that supports it must reset it to `false` whenever
the open drawer's content identity (template key plus record id) changes —
independent of, and in addition to, the content's own effect cleanup — so a
hidden header can never leak from one drawer's content into another.

## 11. Confirm and prompt dialogs

There is no shared modal/prompt component. `components/modal/index.ts` is a
dead stub and must not be treated as, or replaced with the expectation of, a
rendering primitive. The locked convention is:

- A destructive or consequential lifecycle action (Publish, Discard draft,
  Trash, Permanent delete, unsaved-changes exit) that needs an interrupting
  overlay is hand-authored per entity as `<Entity>DrawerDialogs.tsx`, sharing
  only the `cz-publish-confirm*` CSS class convention and the
  click-outside-to-dismiss pattern. This is a styling convention, not a
  shared component; do not extract one without new evidence of a second
  identical consumer under this document's "Abstraction evidence" standard
  in `AGENTS.md`.
- A per-row confirm on a bin/travel surface (arm, then confirm in place, not
  an overlay) is `useInlineConfirm`. It renders nothing itself and must not
  be treated as, or replaced by, an overlay modal.
- Every destructive action on a bin/travel surface must be guarded by one of
  the two mechanisms above. The Tier Edition bin's Move-to-Bin, Publish
  Edition, and in-bin permanent-delete rows currently fire directly on click
  with neither guard, unlike the Tier occupant bin's `useInlineConfirm`-armed
  permanent delete. This is a recorded deviation, not a pattern to copy: a
  new Station or surface must use the occupant bin as its reference, and this
  gap should close before the Edition bin is cited as fully conforming.

## 12. Footer split-button grammar

The default record-footer shape — `CanonicalEntityFooter`/
`EntityActionFooter` as used by Service, Service Category, Package Family,
and Tier Group/System — is one `split` action (status/travel: Disable,
Enable, Move to Trash, with Archive/Trash in overflow) plus a separate
`primary` Publish button. This remains the default shape for a new
conforming Station.

A Station whose record owns a second, independently lifecycled child
collection with its own Publish action (Tier occupant/Edition today) may
instead use the additive **dual independent split**: `split` (LEFT,
backward/travel actions, Move to Bin always last) and `splitForward` (RIGHT,
forward/publish actions), each opening only its own overflow menu through
`menuOnly: true` rather than firing an action on direct click. `menuOnly`
must always route the visible label's click to open or close the menu, never
to `onSelect` — a caller must not wire `onSelect` on the assumption it stays
unreachable by convention alone. When `splitForward` is present, Close
renders beside the LEFT split, not at the far right, so the RIGHT publish
split stands alone. A Station must not invent a third footer shape; it uses
either the default single-split-plus-primary-Publish shape or the dual
independent-split shape exactly as described here (`EntityActionFooter`
supports both through `split`, `splitForward`, and `menuOnly`).

## Related current maps

[Service Station](../code-map/service-station.md) ·
[Service Catalogue](../code-map/service-catalogue.md) ·
[Categories](../code-map/categories.md) ·
[Drawer System](../code-map/drawer-system.md) ·
[Lifecycle and Module State](../code-map/lifecycle-system.md)
