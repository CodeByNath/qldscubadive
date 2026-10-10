// Account Home card — its own data source, deliberately separate from the
// drawer's own fetch (the same two-instance rule Service keeps for its
// catalogue vs. its drawer): refreshing one cannot disturb the other.

import { useEffect, useState } from 'preact/hooks';
import { fetchAccountDetail } from '../api';
import { deriveAccountPillStatus } from '../derive';
import type { AccountPillStatus } from '../derive';
import type { SurfaceCollection } from '@/station-manager/registry/dataSources';

/** Account has no numeric/string native id of its own; this fixed sentinel is the one record's StationRecordId. */
export const ACCOUNT_RECORD_ID = 'account';

export interface AccountCardItem {
  id: string;
  name: string;
  code: string;
  pillStatus: AccountPillStatus;
}

export function useAccountProfileCard(): SurfaceCollection<AccountCardItem> {
  const [item, setItem] = useState<AccountCardItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchAccountDetail()
      .then((detail) => {
        if (cancelled) return;
        setItem({
          id: ACCOUNT_RECORD_ID,
          name: detail.brand.name || 'Account',
          code: detail.brand.code,
          pillStatus: deriveAccountPillStatus(detail),
        });
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof Error ? err.message : 'An unexpected error occurred.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [retryKey]);

  return {
    items: item ? [item] : [],
    loading,
    error,
    refetch: () => setRetryKey((key) => key + 1),
  };
}
