# Roadmap and handover

Current state, open work, and the decisions behind them. Update this file as work lands; move finished items into a Project History milestone (ask the owner first).

## Current state (October 2026)

- Extracted from CompuZign and renamed to QSD: Service and Category Stations, Platform Identifier Station (`QSDS`/`QSDC`), lifecycle engine, drawer kit and notifications, Station Manager, Admin Station at `/station/`, QSD Shell theme.
- Verified on a real WordPress (local) and on staging2: login gate, create → Overview Save → children → Publish → Disable/Enable → Archive/Trash, restore and permanent delete through the API.
- Phase 6 lifecycle completion and the Settings foundation are accepted on `main`.
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

## Next — Settings → Security completion, UI, then Tools

Owner-approved presentation model:

```text
Services Station
├─ Details
├─ Connections
└─ Settings
   ├─ General
   ├─ Tools
   │  ├─ Rezdy importer
   │  ├─ Stripe-related tools
   │  └─ future operational tools
   └─ Security
      ├─ API keys
      ├─ credentials
      ├─ encryption
      ├─ request-key broker
      ├─ permissions
      ├─ rotation/re-seal
      └─ audit
```

`Settings` is a tab pattern inside a Station, not a separate Station. Other Stations may surface `General`, `Tools`, and/or `Security` where relevant. Shared General/Security data is owned once by the platform authority; screen placement never creates duplicate persistence or transfers domain authority.

The current standalone Settings navigation/deck is an accepted foundation implementation but is now a **transitional presentation**. The backend Settings module remains the configuration/security authority while presentation is migrated into the Services Station Settings tab.

### Security Phase 2A — real runtime/API validation

Complete the accepted Security backend against a real non-production WordPress runtime while preserving the existing platform boundary:

- all browser-facing operations remain behind `qsd/v1`; the database is storage/validation infrastructure, not a new query API;
- authenticated WordPress user and caller/component authority are derived server-side;
- provider credentials are encrypted at rest and never projected back to the client;
- request keys are short-lived, bound, hash-only at rest, atomically single-use, replay/expiry/binding safe;
- permissions, safe audit output, fail-closed behavior and key rotation/re-seal are validated on the real runtime;
- one controlled read-only provider authentication/connection check may be used only for Phase 2 evidence.

Do not begin importer/product mapping here.

**Runtime/deployment:** use the existing QSD staging2 WordPress at `staging2.qldscubadive.com.au` and its `/station/` Admin Station. Do not create a second local WordPress/database stack for this phase. When real runtime/browser evidence is required, an exact reviewed candidate may be promoted to `staging` and deployed by the existing GitHub Actions workflow to the guarded staging2 WordPress path. Do not alter the deployment workflow/path/scope. Production remains prohibited.

### Security Phase 2B — Services Station Settings placement

Migrate the configuration presentation into:

`Services Station → Settings → General | Tools | Security`

Requirements:

- do not create a second Settings Station, settings store, security broker, notification system or API family;
- `Services → Connections` remains Service-domain relationships and must not be repurposed for provider credentials;
- `Tools` is the home for operational tools/importers;
- `Security` is the home for API keys and credential/security controls;
- preserve existing Settings backend ownership and `qsd/v1` routes unless a narrowly reviewed route change is required;
- remove/retire the standalone Settings presentation only when its replacement has parity.

### Security Phase 2C — Security → API Keys UI

Build the administrator-facing Security surface in the Station shell.

The API Keys area must provide safe provider credential management without ever reading a secret back into the browser:

- provider identity/name and environment;
- configured / not configured state;
- encryption available/unavailable state;
- administrator permission state;
- write-only add/replace credential flow;
- explicit clear/disconnect flow with confirmation;
- safe success/failure notifications;
- no plaintext secret, request key, request-key hash, encryption key or key fingerprint in browser state, REST payloads, audit display or logs;
- ordinary `manage_qsd` users may see only safe state; secret mutation remains administrator-authorised.

Rezdy belongs under `Tools` when importer work begins; its API credential belongs under `Settings → Security → API Keys`.

### OWNER UI GATE — mandatory stop

As soon as `Services Station → Settings → Security → API Keys` is browser-ready:

1. stop implementation;
2. set the active coordination file to `BLOCKED — OWNER UI REVIEW REQUIRED`;
3. notify the Owner that the Security UI is ready for visual/interaction review;
4. provide the exact candidate SHA and the browser/runtime surface used;
5. do **not** advance by a normal `run the cycle`, `continue the work`, or equivalent instruction.

Work may continue past this gate only after the Owner explicitly accepts the UI or gives corrections. Reviewer/Builder automation must not infer acceptance from another cycle request.

### Security Phase 2D — key rotation/re-seal operator flow

After the Owner UI gate is accepted, complete the rotation operating model around the existing all-or-nothing re-seal engine.

Backend guarantees remain mandatory:

- active and previous master keys stay outside the database;
- every credential is planned/readable before any write;
- re-seal uses a fresh nonce and preserves provider/field binding;
- unreadable/malformed credentials fail the whole operation without partial writes;
- repeated re-seal is idempotent;
- output/audit exposes slot/count/status metadata only, never secret material;
- the previous key can be removed only after successful verification.

The accepted contract currently makes execution shell-only through `wp qsd credentials reseal`. The Security UI may show safe rotation readiness/status and operator guidance. **Do not add a browser/REST trigger for master-key rotation without a separate Owner/Reviewer approval**, because that changes the accepted security boundary.

### Security Phase 2E — closeout and reusable Settings contract

After runtime validation, UI acceptance and rotation closeout:

- update the Settings/Security Code Map to the landed presentation and backend reality;
- update the credential-broker contract if an accepted implementation boundary changed;
- formalise the reusable Station `Settings → General | Tools | Security` presentation rule without duplicating persistence;
- remove stale `Settings Station` / `Connections/Security` presentation wording where the implementation no longer uses it;
- run `npm test` and `npm run docs:check`;
- Reviewer accepts Phase 2 before any Phase 3 Service Manager work starts.

### Phase 3 — Tools and Service Manager

Only after Security Phase 2 is accepted:

- audit the Owner's pre-built Rezdy importer before designing the QSD importer;
- build the Rezdy importer under `Services Station → Settings → Tools`;
- keep provider credentials in Security; Tools request controlled broker authority and never own/read long-lived credentials;
- continue Service Meta / Service Elements / Service Options only after the existing Element-definition identity gate is resolved.

## Later — scuba domain

- **New Stations** per [the AI index](ai-index.md#adding-a-station): e.g. Courses/Trips schedule, Dive Sites, Equipment, Staff. Each gets a prefix, a Code Map, and conformance with the lifecycle contract.
- **Public read API** by Platform ID for the front end, `active` + settled only.
- **Production deploy** — a separate, manually approved workflow with its own guard; not before staging sign-off.

## Working environment

- Clone `CodeByNath/qldscubadive`, then in `wp-content/plugins/qsd-platform/`: `npm ci`, `npm test`. Needs Node 20+ and a PHP 8 CLI.
- Browser checks: staging2 `/station/` with a Platform Manager test user, or a local WordPress with this repo's plugin and theme symlinked into `wp-content`.
- Deploy to staging: push to `staging` (e.g. `git push origin main:staging`). Watch the run in the repo's Actions tab.
