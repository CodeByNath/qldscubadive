// Service Home Bin — the archived/trashed projection and travel actions for the
// lower deck's Bin lane (Phase 6.1).
//
// One Bin for both Bin states and both lifecycle owners on Service Home: Service
// records and the Categories Service Home already presents. It reads the SAME
// authoritative bin lists the owning endpoints already expose
// (`fetchAdminCatalog('archived' | 'trashed')`, `fetchAdminCategories(...)`) and
// runs each action through the owning Station's EXISTING endpoint function —
// restore, move to trash, permanent delete. Legality, restore re-entry (always
// the unmasked Pending state), dependency guards, and Platform ID tombstones stay
// with each backend controller and `StationLifecycle`; this module decides none
// of them. No second lifecycle, status interpretation, or identity path.
//
// The action list per row is the Owner-locked order, Restore always first:
//   Archived → Restore · Move to Trash · Permanently delete
//   Trash    → Restore · Permanently delete

import { useCallback, useEffect, useRef, useState } from 'preact/hooks';
import {
  fetchAdminCatalog,
  permanentDeleteService,
  restoreService,
  trashService,
} from '../api';
import type { ServiceSummary } from '../types';
import {
  fetchAdminCategories,
  permanentDeleteCategory,
  restoreCategory,
  updateCategoryStatus,
} from '@/api/endpoints/admin';
import type { CategoryStationItem } from '@/api/types/admin';

export type ServiceHomeBinKind = 'service' | 'category';
export type ServiceHomeBinState = 'archived' | 'trashed';
export type ServiceHomeBinActionId = 'restore' | 'trash' | 'delete';

export interface ServiceHomeBinRow {
  /** Stable row key — kind plus native id, never the label or position. */
  key:        string;
  kind:       ServiceHomeBinKind;
  id:         number;
  platformId: string;
  name:       string;
  state:      ServiceHomeBinState;
}

export interface ServiceHomeBinAction {
  id:           ServiceHomeBinActionId;
  label:        string;
  destructive?: boolean;
  /** Destructive actions are armed first and confirmed in place (useInlineConfirm). */
  confirm?:     string;
}

const RESTORE: ServiceHomeBinAction = { id: 'restore', label: 'Restore' };
const MOVE_TO_TRASH: ServiceHomeBinAction = {
  id: 'trash', label: 'Move to Trash', destructive: true, confirm: 'Move to Trash?',
};
const PERMANENT_DELETE: ServiceHomeBinAction = {
  id: 'delete', label: 'Permanently delete', destructive: true, confirm: 'Delete permanently? This cannot be undone.',
};

export function serviceHomeBinActions(row: Pick<ServiceHomeBinRow, 'state'>): ServiceHomeBinAction[] {
  return row.state === 'archived'
    ? [RESTORE, MOVE_TO_TRASH, PERMANENT_DELETE]
    : [RESTORE, PERMANENT_DELETE];
}

function serviceRow(summary: ServiceSummary, state: ServiceHomeBinState): ServiceHomeBinRow {
  return {
    key:        `service:${summary.id}`,
    kind:       'service',
    id:         summary.id,
    platformId: summary.platformId,
    name:       summary.title,
    state,
  };
}

function categoryRow(category: CategoryStationItem, state: ServiceHomeBinState): ServiceHomeBinRow {
  return {
    key:        `category:${category.id}`,
    kind:       'category',
    id:         category.id,
    platformId: category.platformId,
    name:       category.name,
    state,
  };
}

export interface ServiceHomeBinSources {
  services:   { archived: ServiceSummary[]; trashed: ServiceSummary[] };
  categories: { archived: CategoryStationItem[]; trashed: CategoryStationItem[] };
}

// The state comes from which authoritative list a record arrived in, so a row
// can never claim a Bin state its owner did not report.
export function projectServiceHomeBinRows(sources: ServiceHomeBinSources): ServiceHomeBinRow[] {
  return [
    ...sources.services.archived.map((s) => serviceRow(s, 'archived')),
    ...sources.services.trashed.map((s) => serviceRow(s, 'trashed')),
    ...sources.categories.archived.map((c) => categoryRow(c, 'archived')),
    ...sources.categories.trashed.map((c) => categoryRow(c, 'trashed')),
  ];
}

// Each action maps 1:1 onto the owning Station's existing endpoint function.
function runOwningAction(row: ServiceHomeBinRow, actionId: ServiceHomeBinActionId): Promise<unknown> {
  if (row.kind === 'service') {
    if (actionId === 'restore') return restoreService(row.id);
    if (actionId === 'trash') return trashService(row.id);
    return permanentDeleteService(row.id);
  }
  if (actionId === 'restore') return restoreCategory(row.id);
  if (actionId === 'trash') return updateCategoryStatus(row.id, 'trashed');
  return permanentDeleteCategory(row.id);
}

// apiClient errors read "API <METHOD> <path> → <status>: <body>". Surface the
// owner's own message (e.g. the Category assigned-Services guard) when present.
function ownerMessage(err: unknown, fallback: string): string {
  const text = err instanceof Error ? err.message : '';
  const body = text.slice(text.indexOf(': ') + 2);
  try {
    const parsed = JSON.parse(body) as { message?: unknown };
    if (typeof parsed.message === 'string' && parsed.message) return parsed.message;
  } catch {
    // Not a JSON body — fall through.
  }
  return fallback;
}

export interface ServiceHomeBinResult {
  rows:           ServiceHomeBinRow[];
  initialLoading: boolean;
  refreshing:     boolean;
  error:          string | null;
  actionError:    { key: string; message: string } | null;
  refetch:        () => void;
  /** Runs one owning-Station action, then reloads the Bin. Resolves true on success. */
  perform:        (row: ServiceHomeBinRow, actionId: ServiceHomeBinActionId) => Promise<boolean>;
}

// Same "has ever loaded" shape as useServiceHomeConnections: a refetch flips
// `refreshing` only and never discards the rows a render is already showing.
export function useServiceHomeBin(onChanged?: () => void): ServiceHomeBinResult {
  const [rows, setRows] = useState<ServiceHomeBinRow[]>([]);
  const [initialLoading, setInitialLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<{ key: string; message: string } | null>(null);
  const initializedRef = useRef(false);
  const onChangedRef = useRef(onChanged);
  onChangedRef.current = onChanged;

  const load = useCallback((): Promise<void> => {
    if (initializedRef.current) setRefreshing(true);
    setError(null);
    return Promise.all([
      fetchAdminCatalog('archived'),
      fetchAdminCatalog('trashed'),
      fetchAdminCategories('archived'),
      fetchAdminCategories('trashed'),
    ])
      .then(([archivedServices, trashedServices, archivedCategories, trashedCategories]) => {
        setRows(projectServiceHomeBinRows({
          services:   { archived: archivedServices.stations, trashed: trashedServices.stations },
          categories: { archived: archivedCategories.categories, trashed: trashedCategories.categories },
        }));
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Could not load the Bin.');
      })
      .finally(() => {
        initializedRef.current = true;
        setInitialLoading(false);
        setRefreshing(false);
      });
  }, []);

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const perform = useCallback(async (row: ServiceHomeBinRow, actionId: ServiceHomeBinActionId): Promise<boolean> => {
    setActionError(null);
    try {
      await runOwningAction(row, actionId);
    } catch (err: unknown) {
      setActionError({ key: row.key, message: ownerMessage(err, 'That action could not be completed.') });
      return false;
    }
    await load();
    onChangedRef.current?.();
    return true;
  }, [load]);

  const refetch = useCallback(() => { void load(); }, [load]);

  return { rows, initialLoading, refreshing, error, actionError, refetch, perform };
}
