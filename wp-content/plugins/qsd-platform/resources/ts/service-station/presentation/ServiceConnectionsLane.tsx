// Service Home Connections lane — a read-only projection of every live
// Category, so a Category with no assigned Services is still reachable
// (Phase 6.2). One compact filter — All / Connected / Unassigned, default All —
// narrows the already-loaded rows in presentation state only: Connected is
// `assigned_count > 0`, Unassigned is `assigned_count === 0`. Changing it never
// refetches or mutates.
//
// Renders through the shared station list system (`cz-station-list`,
// `cz-station-list__cell`, `StationSplitAction`, `StationStatusPill`) in the
// SAME connected-record row grammar Package Manager's own Connections list
// reads with — identity, Platform ID, a labelled count, the lifecycle pill,
// then View — under Service's own row/column classes. No Package presentation
// import, no copied Package CSS: the shared pieces come from the Admin and
// drawer-kit systems both stations already consume.
//
// View opens the existing mature Category drawer by its real numeric id; this
// lane invents no second Category relationship model and performs no mutation
// of its own.

import { useMemo, useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { StationSplitAction } from '@/admin-station/presentation/StationSplitAction';
import { StationStatusPill } from '@/admin-station/presentation/StationStatusPill';
import { PackagesIcon } from '@/admin-station/shell/icons';
import { ServiceDeckRowIdentity } from './ServiceDeckRowIdentity';
import { useServiceHomeConnections } from '../surface/serviceHomeConnections';
import type { ServiceHomeConnectionRow } from '../surface/serviceHomeConnections';
import type { StationIntentDispatch } from '@/station-manager/registry/templateKits';

// The platform's established visible treatment for an existing record whose
// Platform ID is not set — never an empty cell.
const PLATFORM_ID_FALLBACK = 'Not assigned';

export type ServiceConnectionsFilter = 'all' | 'connected' | 'unassigned';

const FILTER_OPTIONS: Array<{ value: ServiceConnectionsFilter; label: string }> = [
  { value: 'all',        label: 'All' },
  { value: 'connected',  label: 'Connected' },
  { value: 'unassigned', label: 'Unassigned' },
];

const FILTER_EMPTY: Record<ServiceConnectionsFilter, string> = {
  all:        'No Categories yet.',
  connected:  'No Categories are connected to a Service yet.',
  unassigned: 'Every Category is connected to a Service.',
};

export function filterServiceConnectionRows(
  rows: ServiceHomeConnectionRow[],
  filter: ServiceConnectionsFilter,
): ServiceHomeConnectionRow[] {
  if (filter === 'connected') return rows.filter((row) => row.connectedCount > 0);
  if (filter === 'unassigned') return rows.filter((row) => row.connectedCount === 0);
  return rows;
}

function ServiceConnectionRow({ row, onIntent }: {
  row: ServiceHomeConnectionRow;
  onIntent: StationIntentDispatch;
}): VNode {
  return (
    <li class="cz-station-list__row cz-station-list__row--service-connections">
      <ServiceDeckRowIdentity icon={<PackagesIcon />} name={row.name} compact />
      <div class="cz-station-list__cell cz-service-deck__field">
        <span class="cz-service-deck__field-label">Platform ID</span>
        {row.platformId || PLATFORM_ID_FALLBACK}
      </div>
      <div class="cz-station-list__cell cz-service-deck__field">
        <span class="cz-service-deck__field-label">Services</span>
        <span class="cz-service-deck__count">{row.connectedCount}</span>
      </div>
      <span class="cz-station-list__cell">
        {/* The Presentation Status Contract owns every status→label/class
            mapping; this lane defines none of its own. `module` is the pill
            variant the connected-record row reads with. */}
        <StationStatusPill status={row.status} pillVariant="module" />
      </span>
      <div class="cz-station-list__cell cz-service-deck__row-actions">
        <StationSplitAction
          actions={[{ id: 'view-category', label: 'View' }]}
          controlLabel={row.name}
          onAction={(actionId) => onIntent(row.id, actionId)}
        />
      </div>
    </li>
  );
}

export function ServiceConnectionsLane({ onIntent }: { onIntent: StationIntentDispatch }): VNode {
  const { rows, initialLoading, error } = useServiceHomeConnections();
  const [filter, setFilter] = useState<ServiceConnectionsFilter>('all');
  const visibleRows = useMemo(() => filterServiceConnectionRows(rows, filter), [rows, filter]);

  if (initialLoading) return <p class="cz-station-empty">Loading Category connections…</p>;
  if (error) return <p class="cz-station-empty" role="alert">{error}</p>;

  return (
    <div class="cz-service-connections">
      <div class="cz-service-connections__toolbar">
        <select
          class="cz-tf-control cz-tf-select"
          aria-label="Filter Categories by connection"
          value={filter}
          onChange={(event) => setFilter(event.currentTarget.value as ServiceConnectionsFilter)}
        >
          {FILTER_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
        </select>
      </div>
      {visibleRows.length === 0 ? (
        <p class="cz-station-empty">{rows.length === 0 ? FILTER_EMPTY.all : FILTER_EMPTY[filter]}</p>
      ) : (
        <ul class="cz-station-list">
          {visibleRows.map((row) => <ServiceConnectionRow key={row.id} row={row} onIntent={onIntent} />)}
        </ul>
      )}
    </div>
  );
}
