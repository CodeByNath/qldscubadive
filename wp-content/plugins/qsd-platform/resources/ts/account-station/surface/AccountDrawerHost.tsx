// AccountDrawerHost — the Admin Station host adapter for the Account drawer.
//
// Mirrors ServiceDrawerHost: a thin translator mounting the neutral
// AccountDrawerContent composition (the shared EntityDrawer/Station Manager
// kit), never a second drawer implementation. Account has no per-record id to
// resolve — the composition's own useAccountStation() call does the one read,
// separate from the Home card's useAccountCard (the same two-instance rule
// Service keeps).

import { useMemo, useRef } from 'preact/hooks';
import type { VNode } from 'preact';
import type { EntityDrawerHostBridge } from '@/drawer-kit/entityDrawerHost';
import { AccountDrawerContent } from '@/entity-drawers/account/AccountDrawerContent';
import type { DrawerContentProps } from '@/station-manager/drawerTypes';

export function AccountDrawerHost({ mode, onClose, onSaved, setFooter, setCloseGuard }: DrawerContentProps): VNode {
  const closeRef  = useRef(onClose);         closeRef.current  = onClose;
  const footerRef = useRef(setFooter);       footerRef.current = setFooter;
  const guardRef  = useRef(setCloseGuard);   guardRef.current  = setCloseGuard;
  const savedRef  = useRef(onSaved);         savedRef.current  = onSaved;

  const bridge = useMemo<EntityDrawerHostBridge>(() => ({
    close:         () => closeRef.current(),
    setFooter:     (footer) => footerRef.current?.(footer),
    setCloseGuard: (guard)  => guardRef.current?.(guard),
    onMutationComplete: () => savedRef.current(),
  }), []);

  return (
    <AccountDrawerContent
      initialTab="details"
      initialEdit={mode === 'edit'}
      bridge={bridge}
    />
  );
}
