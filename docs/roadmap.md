# Roadmap and handover

Current state, open work, and the decisions behind them. Update this file as work lands; move finished items into a Project History milestone (ask the owner first).

## Current state (September 2026)

- Extracted from CompuZign and renamed to QSD: Service and Category Stations, Platform Identifier Station (`QSDS`/`QSDC`), lifecycle engine, drawer kit and notifications, Station Manager, Admin Station at `/station/`, QSD Shell theme.
- Verified on a real WordPress (local) and on staging2: login gate, create → Overview Save → children → Publish → Disable/Enable → Archive/Trash, restore and permanent delete through the API.
- `npm test` and CI pass. Pushing `staging` deploys to staging2 only.

## Decisions (do not undo without the owner)

- **WordPress is runtime and storage only.** No platform UI outside `/station/`; the public website is a separate front end that reads the API.
- **Entities are private to WordPress** (`public: false`, no `/wp/v2`, archives, search or sitemaps). Public reads will go through `qsd/v1` routes that return `active` records and settled content only — never drafts.
- **Platform IDs are the public key.** Prefixes are permanent once records exist; new Stations add their own `QSD…` prefix.
- **npm stays on developer machines and CI.** The server receives built files only; `dist/` is not committed.
- **"Service" stays the internal name** (`qsd_service`, `/admin/services`, `QSDS`) for courses, dives, snorkelling and trips. Admin labels (e.g. "Experiences") are a later cosmetic change. Retail gear will be its own **Product** Station; public URL wording is decided by the front end, not by these internal names.
- **The platform never creates accounts.** Business users get the `qsd_platform_manager` role in WordPress.
- **Staging safety.** The deploy refuses any `STAGING_WP_PATH` other than `/home/customer/www/staging2.qldscubadive.com.au/public_html`, and writes only `wp-content/plugins/qsd-platform/` and `wp-content/themes/qsd-shell/`. The SSH account can reach the live site too — never add a deploy target, command, or `--delete` scope outside those two folders without the owner's explicit approval. There is no production deploy yet.

## Phase 6 — lifecycle completion (next)

1. **Bin surface — done (Phase 6.1, accepted).** Service Home's `Bin` lane (`service-station/presentation/ServiceBinLane.tsx`, `surface/serviceHomeBin.ts`) lists archived and trashed Services and Categories — name, Platform ID, Archived/Trash pill, one split action (Restore first; Move to Trash on archived rows; Permanently delete), and an All / Archived / Trash filter — through the existing restore/trash/delete endpoints, with `useInlineConfirm` for destructive actions. Owner decision: permanent delete is legal from either Bin state (`StationLifecycle::canDelete`).
2. **Reachable Categories — built in Phase 6.2 (awaiting Reviewer acceptance).** Service Home's Connections lane lists every live Category with an All / Connected / Unassigned filter (default All) over the already-loaded rows; View opens the existing Category drawer by native id.
3. **Server-side transitions.** `ServiceController::updateStatus` and `AdminCategoriesController::updateStatus` use `StationLifecycle::applyStatus` (any target). Route them through the strict per-action rules and refuse `active` unless the Overview is complete and settled.
4. **Atomic Publish.** `useServiceStation.publishService` activates even if settle fails; stop on failure, or add one server `publish` route that settles and activates together.
5. **Trash confirmation.** Moving a saved Service to Trash from the drawer has no confirm dialog; use the `<Entity>DrawerDialogs` convention (lifecycle contract §11).
6. Add mounted regressions for each, update the lifecycle Code Map's *Known gaps*, and extend `drawer-module-entry`.

## After Phase 6 — scuba domain

- **Service details module** (price, duration, max depth, certification level, photos) as a new Service module following Inclusions/FAQs — not Overview fields.
- **New Stations** per [the AI index](ai-index.md#adding-a-station): e.g. Courses/Trips schedule, Dive Sites, Equipment, Staff. Each gets a prefix, a Code Map, and conformance with the lifecycle contract.
- **Public read API** by Platform ID for the front end, `active` + settled only.
- **Production deploy** — a separate, manually approved workflow with its own guard; not before staging sign-off.

## Working environment

- Clone `CodeByNath/qldscubadive`, then in `wp-content/plugins/qsd-platform/`: `npm ci`, `npm test`. Needs Node 20+ and a PHP 8 CLI.
- Browser checks: staging2 `/station/` with a Platform Manager test user, or a local WordPress with this repo's plugin and theme symlinked into `wp-content`.
- Deploy to staging: push to `staging` (e.g. `git push origin main:staging`). Watch the run in the repo's Actions tab.
