// Neutral Account drawer contracts — host-agnostic, mirroring
// categoryDrawerTypes.ts. Admin Station is the only host today, but the
// composition names neither host nor a numeric/string record id — Account
// is a singleton with no 'new' sentinel to represent.

import type { EntityDrawerHostBridge } from '@/drawer-kit/entityDrawerHost';
import type { DrawerTabId } from '@/drawer-kit/DrawerTabs';

export interface AccountDrawerContentProps {
  initialTab?: DrawerTabId;
  initialEdit?: boolean;
  bridge: EntityDrawerHostBridge;
}

export type AccountExitDialog = 'unsaved' | null;
