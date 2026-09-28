/*
 * FILE INDEX
 *
 * COMPLETENESS             Overview completeness checks
 * MODULE_STATUS            Service overview resolver
 * STATUS_PRESENTATION      Status metadata, dots, and pills
 * CATALOGUE_STATUS         Service catalogue buckets and labels
 *
 * Search: SECTION: COMPLETENESS
 *         SECTION: MODULE_STATUS
 *         SECTION: STATUS_PRESENTATION
 *         SECTION: CATALOGUE_STATUS
 */

import type { ServiceItem } from '@/api/types/service';
// Targets the station's './types' module, not its public barrel: useServiceStation
// imports this file, so going through the barrel would close a cycle.
import type { OverviewDraftData, ServiceSummary } from '@/service-station/types';
import {
  PILL_META,
  PRESENTATION_PILL,
  STATUS_DOT_COLOR,
  STATUS_DOT_FAINT_COLOR,
  STATUS_DOT_CLASS,
  STATUS_DOT_FAINT_CLASS,
  LEGACY_UNKNOWN_PILL,
} from '../schema/presentation';
import type { PillMeta } from '../schema/presentation';

// ── Status resolvers ──────────────────────────────────────────────────────────

// ===========================================================================
// SECTION: COMPLETENESS
// ===========================================================================
// Used by both the pill resolvers and the notification generators so the
// field-completeness rule lives in exactly one place.

export interface OverviewCompleteness {
  title:    boolean;
  excerpt:  boolean;
  category: boolean;
  content:  boolean;
  complete: boolean;
}

export function checkOverviewCompleteness(service: ServiceItem): OverviewCompleteness {
  const title    = !!service.title.trim();
  const excerpt  = !!service.excerpt?.trim();
  const category = service.categories.length > 0;
  const content  = !!service.content.trim();
  // excerpt temporarily excluded from completeness gate
  return { title, excerpt, category, content, complete: title && category && content };
}

export function checkOverviewCompletenessFromDraft(draft: OverviewDraftData): OverviewCompleteness {
  const title    = !!draft.title.trim();
  const excerpt  = !!draft.excerpt.trim();
  const category = draft.category_ids.length > 0;
  const content  = !!draft.content.trim();
  // excerpt temporarily excluded from completeness gate
  return { title, excerpt, category, content, complete: title && category && content };
}

// ===========================================================================
// SECTION: MODULE_STATUS
// ===========================================================================

export interface OverviewStatusOpts {
  platformStatus:   string;  // 'active' | 'disabled' | 'archived' | 'trashed'
  moduleTransition: string;  // 'settled' | 'pending' | 'not-configured'
  // Platform-visible presentation mask set by the drawer's explicit Disable
  // action (never inferred from a Service that was simply never activated —
  // see ServiceMeta.previous_platform_status). Takes precedence over every
  // other state, including not-configured: Disable masks the whole record.
  disabled?:        boolean;
}

export function resolveOverviewStatus(
  service: ServiceItem,
  opts: OverviewStatusOpts,
  draft?: OverviewDraftData | null,
): string {
  const { platformStatus, moduleTransition, disabled } = opts;

  if (disabled) return 'disabled';

  // not-configured: module has no content and no draft — always dim.
  if (moduleTransition === 'not-configured') return 'pending-dim';

  // Prefer draft completeness when a draft exists; fall back to canonical.
  const { complete } = draft
    ? checkOverviewCompletenessFromDraft(draft)
    : checkOverviewCompleteness(service);

  if (!complete) return 'pending-dim';

  // Complete + pending (draft exists) → pending-full.
  if (moduleTransition === 'pending') return 'pending-full';

  // Complete + settled, but service is not yet active → still pending-full (not disabled).
  if (platformStatus !== 'active') return 'pending-full';

  return 'active';
}

// ===========================================================================
// SECTION: STATUS_PRESENTATION
// ===========================================================================
// Pill/dot metadata is owned by schema/presentation.ts (the Presentation Status
// Contract chokepoint — S1a). This file keeps the resolver + renderer layer and
// re-exports the combined map for existing consumers.

export const STATUS_PILL_MAP: Record<string, { dot: string; cls: string; label: string }> =
  Object.fromEntries(
    Object.entries(PILL_META).map(([status, meta]) => [
      status,
      { dot: STATUS_DOT_COLOR[status] ?? STATUS_DOT_FAINT_COLOR, ...meta },
    ]),
  );

export function statusDotColor(status: string): string {
  return STATUS_DOT_COLOR[status] ?? STATUS_DOT_FAINT_COLOR;
}

// Token-based status-dot modifier class (mirrors statusDotColor) so tables can use
// the reusable .cz-admin-status-dot--* classes instead of inline colour styles.
export function statusDotClass(status: string): string {
  return STATUS_DOT_CLASS[status] ?? STATUS_DOT_FAINT_CLASS;
}

export function renderModuleStatus(status: string) {
  const pill = STATUS_PILL_MAP[status] ?? LEGACY_UNKNOWN_PILL;
  return (
    <>
      <span class="cz-admin-status-dot" style={`color:${pill.dot}`} />
      <span class={`cz-module-status-pill ${pill.cls}`}>{pill.label}</span>
    </>
  );
}

// ===========================================================================
// SECTION: CATALOGUE_STATUS
// ===========================================================================
// Moved from ServiceCatalogStation in S3b so the catalog TableSchema can
// project it. Filter buckets and the display pill stay separate on purpose:
// the bucket drives filtering; the label distinguishes a live service with
// unsettled changes without altering which bucket it filters into.

export type StationStatus = 'active' | 'pending' | 'drafts' | 'disabled';

// Pill metadata delegates to the Presentation Status Contract chokepoint (S1a);
// the station filter buckets 'pending' and 'drafts' both present as Pending.
export const STATION_STATUS_PILL: Record<StationStatus, PillMeta> = {
  'active':   PRESENTATION_PILL.active,
  'pending':  PRESENTATION_PILL.pending,
  'drafts':   PRESENTATION_PILL.pending,
  'disabled': PRESENTATION_PILL.disabled,
};

export function resolveStationStatus(station: ServiceSummary): StationStatus {
  if (station.platform_status === 'disabled') {
    // The Disable action's platform-visible mask (ServiceMeta.previous_platform_status):
    // non-empty means Disable was explicitly applied — genuinely Disabled.
    // Empty means the Service is 'disabled' only because it has never been
    // published, OR because Enable just lifted the mask without republishing
    // (Enable is not Publish — it leaves the Service pending review) — Pending
    // either way, never Disabled.
    return station.previous_platform_status ? 'disabled' : 'pending';
  }
  if (station.has_drafts) return 'drafts';
  if (Object.values(station.module_status).some((v) => v === 'pending')) return 'pending';
  return 'active';
}

// Display-only pill (label + class). Decoupled from resolveStationStatus (which stays
// the filter bucket) so the label can distinguish a live service with unsettled changes
// from a never-published one — without altering filtering. Frontend visibility is
// gated only by platform_status; "Active · changes pending" still means the service
// is live to public API consumers.
export function stationStatusLabel(station: ServiceSummary): PillMeta {
  if (station.platform_status === 'disabled') {
    // Mirrors resolveStationStatus's mask check — see its comment.
    return station.previous_platform_status
      ? STATION_STATUS_PILL.disabled
      : STATION_STATUS_PILL.pending;
  }
  const hasUnsettled =
    station.has_drafts ||
    Object.values(station.module_status).some((v) => v === 'pending');
  return hasUnsettled
    ? { cls: STATION_STATUS_PILL.active.cls, label: 'Active · changes pending' }
    : STATION_STATUS_PILL.active;
}
