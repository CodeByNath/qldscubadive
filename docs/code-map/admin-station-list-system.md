# Admin Station List System

## Purpose and ownership

One record-list surface for the whole Admin Station, declared in `resources/ts/admin-station/styles/admin-station.css` and named by both markup shapes that use it. Admin Station owns it as shell presentation; consuming it transfers no domain authority, and a consuming surface owns only its own column template and cell content.

## The two shapes

- **Table** — the Service Catalogue, `resources/ts/service-station/presentation/ServiceCatalogue.tsx`. Its `<thead>` supplies the column labels, so its cells carry none.
- **List** — Service Home's Connections and Bin lanes and the Settings → General creation launchers, `resources/ts/service-station/presentation/ServiceConnectionsLane.tsx`, `ServiceBinLane.tsx`, and `ServiceCreateLaunchers.tsx`. A list has no header row, so each cell carries its own label through `.cz-service-deck__field-label`. A future Station's list lane adds its own `.cz-<station>-deck__field-label`.

A list stays a list. No table markup crosses into a lane, and nothing that is not already a list is turned into one.

## What is shared, and what is not

Declared once, in the blocks the catalogue's table selectors and the `cz-station-list__*` selectors both name:

- cell padding, `text-align`, cell borders;
- the elevated cell background and muted cell colour;
- the row's start and end radii, taken by the first and last cell;
- the rhythm between rows, and the list's text colour and size.

Declared apart, because each shape needs its own:

- the layout engine — `border-collapse` / `border-spacing` for the table, `display: flex` with a token `gap` for the list;
- the column template each list owns: `.cz-station-list__row--service-connections` and `--service-settings` (Service's own column counts) — a surface adds a selector to this family, it does not reuse another surface's template.

Every value is a `--station-*` token. A surface that needs the list adds its selector to these blocks; it does not author a second family.

## Cells

`.cz-station-list__cell` is the label/value default: a grid box that centres its own content, because a grid cell has no table box to centre it against. Two cells restate `display` for their own axis, which is why they need no wrapper element:

- identity — `ServiceDeckRowIdentity`, a flex row of icon and copy;
- actions — `.cz-service-deck__row-actions`, a flex row aligned to the end.

The status pill sizes to its text and carries `justify-self: start`, so it sits inside a cell rather than being one.

## Stacked mode

Both shapes stack together at a 980px component breakpoint in `admin-station-responsive.css`. The catalogue table declares `min-width: 920px`, so stacking there retires the band in which it scrolled sideways inside its wrapper instead of collapsing. Stacked, the row rather than its cells becomes the card: the shared cell borders and radii are cleared for both shapes together and re-drawn as a single divider between stacked cells. The catalogue's cells then take their label from `data-label`; a deck cell already carries one.

## Validation

Run `npm run contract:admin-station-css` (every declared class is emitted, and feature CSS paints no control), `npm run contract:station-tabset` (Service Home's lanes use the shared classes), `npm run build`, and `npm run docs:check` from the plugin root, plus browser inspection.

## Related Code Maps

[Admin Station Styles](admin-station-styles.md), [Admin Station Cards](admin-station-cards.md), and [Service Catalogue](service-catalogue.md).
