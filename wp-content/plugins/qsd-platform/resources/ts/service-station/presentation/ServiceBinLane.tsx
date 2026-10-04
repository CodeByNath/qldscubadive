// Service Home Bin lane — the one Bin travel surface for archived and trashed
// Service and Category records (Phase 6.1; lifecycle contract §5 places Restore
// here, never inside the drawer).
//
// One compact row per record in the shared station list system:
//   name · Platform ID · travel pill (Archived / Trashed) · one split action
// Restore is always the primary half; the destructive actions live in the same
// control's menu. A destructive choice is armed first and confirmed in place
// through `useInlineConfirm` (contract §11) — never an overlay, and never a row
// of scattered buttons.
//
// Presentation only: rows and actions come from `surface/serviceHomeBin.ts`,
// which runs each action through the owning Station's existing endpoint. This
// file makes no API call and interprets no lifecycle rule.

import { useEffect, useRef, useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { StationSplitAction } from '@/admin-station/presentation/StationSplitAction';
import { PackagesIcon, ServicesIcon } from '@/admin-station/shell/icons';
import { TravelStatusPill } from '@/drawer-kit/ui/TravelStatusPill';
import { useInlineConfirm } from '@/hooks/useInlineConfirm';
import { ServiceDeckRowIdentity } from './ServiceDeckRowIdentity';
import {
  serviceHomeBinActions,
  useServiceHomeBin,
  type ServiceHomeBinAction,
  type ServiceHomeBinActionId,
  type ServiceHomeBinRow,
} from '../surface/serviceHomeBin';

// The platform's established visible treatment for an existing record whose
// Platform ID is not set — never an empty cell.
const PLATFORM_ID_FALLBACK = 'Not assigned';

const KIND_LABEL: Record<ServiceHomeBinRow['kind'], string> = {
  service:  'Service',
  category: 'Category',
};

interface RowProps {
  row:       ServiceHomeBinRow;
  armed:     ServiceHomeBinAction | null;
  busy:      boolean;
  onAction:  (row: ServiceHomeBinRow, actionId: ServiceHomeBinActionId) => void;
  onConfirm: (row: ServiceHomeBinRow) => void;
  onCancel:  () => void;
}

function ServiceBinRow({ row, armed, busy, onAction, onConfirm, onCancel }: RowProps): VNode {
  const actions = serviceHomeBinActions(row);

  return (
    <li class="cz-station-list__row cz-station-list__row--service-bin" data-bin-key={row.key}>
      <ServiceDeckRowIdentity
        icon={row.kind === 'service' ? <ServicesIcon /> : <PackagesIcon />}
        name={row.name}
        reference={KIND_LABEL[row.kind]}
        compact
      />
      <div class="cz-station-list__cell cz-service-deck__field">
        <span class="cz-service-deck__field-label">Platform ID</span>
        {row.platformId || PLATFORM_ID_FALLBACK}
      </div>
      <span class="cz-station-list__cell">
        <TravelStatusPill status={row.state} />
      </span>
      <div class="cz-station-list__cell cz-service-deck__row-actions">
        {armed ? (
          <span class="cz-service-bin__confirm" role="group" aria-label={`${armed.label} ${row.name}`}>
            <span class="cz-service-bin__prompt">{armed.confirm}</span>
            <button
              type="button"
              class="cz-service-deck__button cz-service-deck__button--danger"
              disabled={busy}
              onClick={() => onConfirm(row)}
            >
              {busy ? 'Working…' : 'Confirm'}
            </button>
            <button type="button" class="cz-service-deck__button" disabled={busy} onClick={onCancel}>
              Cancel
            </button>
          </span>
        ) : (
          <StationSplitAction
            actions={actions.map((action) => ({
              id:          action.id,
              label:       action.id === 'restore' && busy ? 'Restoring…' : action.label,
              destructive: action.destructive,
              disabled:    busy,
            }))}
            controlLabel={row.name}
            onAction={(actionId) => onAction(row, actionId as ServiceHomeBinActionId)}
          />
        )}
      </div>
    </li>
  );
}

export function ServiceBinLane({ active, onChanged }: {
  /** True while the Bin lane is the selected deck lane — selecting it reloads the Bin. */
  active:     boolean;
  /** The surface's own refresh, so Details reflects a restore or delete. */
  onChanged?: () => void;
}): VNode {
  const { rows, initialLoading, error, actionError, refetch, perform } = useServiceHomeBin(onChanged);
  const confirm = useInlineConfirm<string>();
  const [armed, setArmed] = useState<ServiceHomeBinAction | null>(null);

  // Records reach the Bin from the drawer while this lane sits hidden, so the
  // lane reloads each time it is selected (its mount already loaded once).
  const wasActive = useRef(active);
  useEffect(() => {
    if (active && !wasActive.current) refetch();
    wasActive.current = active;
  }, [active, refetch]);

  const handleAction = (row: ServiceHomeBinRow, actionId: ServiceHomeBinActionId) => {
    const action = serviceHomeBinActions(row).find((candidate) => candidate.id === actionId);
    if (!action) return;
    if (action.confirm) {
      setArmed(action);
      confirm.request(row.key);
      return;
    }
    void confirm.run(row.key, () => perform(row, action.id));
  };

  const handleConfirm = (row: ServiceHomeBinRow) => {
    if (!armed) return;
    const actionId = armed.id;
    void confirm.run(row.key, () => perform(row, actionId)).then(() => setArmed(null));
  };

  const handleCancel = () => {
    confirm.cancel();
    setArmed(null);
  };

  if (initialLoading) return <p class="cz-station-empty">Loading the Bin…</p>;
  if (error) return <p class="cz-station-empty" role="alert">{error}</p>;

  const failedRow = actionError ? rows.find((row) => row.key === actionError.key) : undefined;

  return (
    <div class="cz-service-bin">
      {actionError && (
        <p class="cz-service-bin__error" role="alert">
          {failedRow ? `${failedRow.name}: ` : ''}{actionError.message}
        </p>
      )}
      {rows.length === 0 ? (
        <p class="cz-station-empty">The Bin is empty.</p>
      ) : (
        <ul class="cz-station-list">
          {rows.map((row) => (
            <ServiceBinRow
              key={row.key}
              row={row}
              armed={confirm.pendingId === row.key ? armed : null}
              busy={confirm.busyId === row.key}
              onAction={handleAction}
              onConfirm={handleConfirm}
              onCancel={handleCancel}
            />
          ))}
        </ul>
      )}
    </div>
  );
}
