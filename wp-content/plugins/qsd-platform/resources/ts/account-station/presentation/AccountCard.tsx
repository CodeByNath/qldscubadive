// Account Home card — the registered template kit for the 'account-card'
// data source. One record, so one ReadBlock; no grid, no pagination, no
// filter. Reuses the shared drawer-kit module-entry card verbatim rather
// than inventing a parallel card primitive.

import type { VNode } from 'preact';
import { ReadBlock } from '@/drawer-kit/ReadBlock';
import type { TemplateKitProps } from '@/station-manager/registry/templateKits';
import type { AccountCardItem } from '../surface/useAccountCard';

export function AccountCard({ items, loading, error, onIntent }: TemplateKitProps): VNode {
  if (loading) {
    return <p class="cz-station-empty">Loading…</p>;
  }
  if (error) {
    return <p class="cz-station-empty">{error}</p>;
  }

  const item = items[0] as AccountCardItem | undefined;
  if (!item) {
    return <p class="cz-station-empty">Account is not available.</p>;
  }

  return (
    <ReadBlock
      title={item.name}
      subtitle={item.code || undefined}
      status={item.pillStatus}
      actions={[{ id: 'open', label: 'Open', onSelect: () => onIntent(item.id, 'view') }]}
    >
      <p>Business profile, logo, and favicon.</p>
    </ReadBlock>
  );
}
