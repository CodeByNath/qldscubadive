// Shared pool item shapes are owned by the neutral pool contract module; this
// file consumes them like any other station does.
import type { InclusionItem, FaqItem } from './pools';

// ── Category station (S6) ─────────────────────────────────────────────────────

export interface CategoryOverviewDraft {
  name:        string;
  description: string;
}

// The /admin/categories list-route projection: draft-preferred overview fields
// (name/description show the draft when one exists) + the lifecycle envelope.
// Slug is settled-only (immutable, D5). assigned_count is the D6 delete-guard
// predicate: services assigned to the term in any status. Category carries no
// group concept — the retired Service Category Group selector is gone.
export interface CategoryStationItem {
  id:                       number;
  platformId:               string;
  name:                     string;
  slug:                     string;
  description:              string;
  platform_status:          'active' | 'disabled' | 'archived' | 'trashed';
  previous_platform_status: 'active' | 'disabled' | '';
  module_status:            { overview: string };
  has_draft:                boolean;
  assigned_count:           number;
}

export interface CategoryListResponse {
  categories: CategoryStationItem[];
}

// Shared by create / settle / revert / status / restore — each returns the full
// refreshed projection.
export interface CategoryMutationResponse {
  success:  boolean;
  message?: string;
  category: CategoryStationItem;
}

export interface CategoryOverviewSaveResponse {
  success:       boolean;
  draft:         CategoryOverviewDraft;
  module_status: { overview: string };
}

// Success shape only. A D6 guard failure is an HTTP 409 — apiClient throws, and
// the error text carries the JSON body { message, assigned_count } for the
// surface's inline-confirm error path to parse.
export interface CategoryDeleteResponse {
  success: boolean;
  deleted: number;
  platformId: string;
}
