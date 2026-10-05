# Roadmap and handover

Current state, open work, and the decisions behind them. Update this file as work lands; move finished items into a Project History milestone (ask the owner first).

## Current state (October 2026)

- Extracted from CompuZign and renamed to QSD: Service and Category Stations, Platform Identifier Station (`QSDS`/`QSDC`), lifecycle engine, drawer kit and notifications, Station Manager, Admin Station at `/station/`, QSD Shell theme.
- Verified on a real WordPress (local) and on staging2: login gate, create → Overview Save → children → Publish → Disable/Enable → Archive/Trash, restore and permanent delete through the API.
- Phase 6 lifecycle completion is accepted on `main`.
- `npm test` and CI pass. Pushing `staging` deploys to staging2 only.

## Decisions (do not undo without the owner)

- **WordPress is runtime and storage only.** No platform UI outside `/station/`; the public website is a separate front end that reads the API.
- **Entities are private to WordPress** (`public: false`, no `/wp/v2`, archives, search or sitemaps). Public reads will go through `qsd/v1` routes that return `active` records and settled content only — never drafts.
- **Platform IDs are the public key.** Prefixes are permanent once records exist; new Stations add their own `QSD…` prefix.
- **npm stays on developer machines and CI.** The server receives built files only; `dist/` is not committed.
- **"Service" stays the internal name** (`qsd_service`, `/admin/services`, `QSDS`) for courses, dives, snorkelling and trips. Admin labels (e.g. "Experiences") are a later cosmetic change. Retail gear will be its own **Product** Station; public URL wording is decided by the front end, not by these internal names.
- **The platform never creates accounts.** Business users get the `qsd_platform_manager` role in WordPress.
- **Staging safety.** The deploy refuses any `STAGING_WP_PATH` other than `/home/customer/www/staging2.qldscubadive.com.au/public_html`, and writes only `wp-content/plugins/qsd-platform/` and `wp-content/themes/qsd-shell/`. The SSH account can reach the live site too — never add a deploy target, command, or `--delete` scope outside those two folders without the owner's explicit approval. There is no production deploy yet.

## Phase 6 — lifecycle completion (done)

1. **Bin surface — done (Phase 6.1, accepted).** Service Home's `Bin` lane (`service-station/presentation/ServiceBinLane.tsx`, `surface/serviceHomeBin.ts`) lists archived and trashed Services and Categories — name, Platform ID, Archived/Trash pill, one split action (Restore first; Move to Trash on archived rows; Permanently delete), and an All / Archived / Trash filter — through the existing restore/trash/delete endpoints, with `useInlineConfirm` for destructive actions. Owner decision: permanent delete is legal from either Bin state (`StationLifecycle::canDelete`).
2. **Reachable Categories — done (Phase 6.2, accepted).** Service Home's Connections lane lists every live Category with an All / Connected / Unassigned filter (default All) over the already-loaded rows; View opens the existing Category drawer by native id.
3. **Server-side transitions — done (Phase 6.3, accepted).** Both `/status` routes resolve targets through `StationLifecycle::statusRouteTransition` (the permissive `applyStatus` is removed): `active` = Publish and needs a complete, settled Overview; `archived`/`trashed` follow archive/trash; a direct `disabled` and illegal sources get a 422 that writes nothing; Service's legacy `is_active` follows the same rules.
4. **Atomic Publish — done (Phase 6.4, accepted) as stop-on-settle-failure.** `publishService` and `publishCategory` stop when settle fails: no activation request, no success, record unchanged (`regression:publish-activation-guard`). No new server `publish` route was added; the two-request gap stays in the lifecycle Code Map's *Known gaps*.
5. **Trash confirmation — done (Phase 6.5, accepted).** A saved Service's drawer Move to Trash opens a confirmation in `ServiceDrawerDialogs` (lifecycle contract §11); Cancel sends nothing, Confirm trashes once, failure keeps the dialog open. The local `new` discard is unchanged.
6. **Regression closeout — done (Phase 6.6, accepted).** `regression:drawer-trash-confirm` covers Service and Category drawer Trash confirmation; `drawer-module-entry` pins the dialog wiring; Bin, reachable-Category, Publish-guard, and PHP transition tests remain in `npm test`.

## Next — Settings foundation, then connections and import

Owner sequencing: Settings and platform connections come before the Service importer and deeper imported-Service work.

1. **Settings foundation — built (awaiting Reviewer acceptance).** A Settings Station (`src/Modules/Settings/`, `resources/ts/settings-station/`) hosts configuration Tools; it owns no domain record or Platform ID family. See [Settings Station](code-map/settings-station.md).
2. **Connections/Security — foundation built (awaiting Reviewer acceptance).** Provider-neutral connection store; secrets are saved server-side and never projected back; Connectors read their own values through `ConnectorCredentials`. Open gates: rotating request keys, secret encryption at rest, credential permission level.
3. **Rezdy Connector — connection seam built (awaiting Reviewer acceptance).** Environment + API key configuration and connection state only; no HTTP calls, no mapping.
4. **Service importer — blocked on Owner input.** Rezdy product/service mapping and the import flow wait for the Owner's pre-built Rezdy importer; review it before any importer design.
5. **Service Meta — schema built (awaiting Reviewer acceptance); Service value module next.** Settings owns field definitions (text, long text, number, yes/no, select, image, gallery, repeater) with stable `fld_` ids and retire/restore; Service will own values per the [Service Meta schema contract](architecture/service-meta-schema-contract.md). Descriptive Service details (duration, max depth, certification level, photos) arrive through Service Meta, not as hard-coded Overview fields.

## Later — scuba domain

- **New Stations** per [the AI index](ai-index.md#adding-a-station): e.g. Courses/Trips schedule, Dive Sites, Equipment, Staff. Each gets a prefix, a Code Map, and conformance with the lifecycle contract.
- **Public read API** by Platform ID for the front end, `active` + settled only.
- **Production deploy** — a separate, manually approved workflow with its own guard; not before staging sign-off.

## Working environment

- Clone `CodeByNath/qldscubadive`, then in `wp-content/plugins/qsd-platform/`: `npm ci`, `npm test`. Needs Node 20+ and a PHP 8 CLI.
- Browser checks: staging2 `/station/` with a Platform Manager test user, or a local WordPress with this repo's plugin and theme symlinked into `wp-content`.
- Deploy to staging: push to `staging` (e.g. `git push origin main:staging`). Watch the run in the repo's Actions tab.
