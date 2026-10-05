// The Settings Home data source. Settings has no record collection — each Tool
// lane reads its own Settings endpoint — so the surface host receives an
// honest empty collection rather than a fabricated record.

import type { SurfaceCollection } from '@/station-manager/registry/dataSources';

const noop = () => {};
const EMPTY: SurfaceCollection = { items: [], loading: false, error: null, refetch: noop };

export function useSettingsHome(): SurfaceCollection {
  return EMPTY;
}
