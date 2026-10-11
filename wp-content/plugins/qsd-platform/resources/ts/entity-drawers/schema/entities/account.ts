import type { EntitySchema } from '@/drawer-kit/schema/types';
import { accountBrandShell } from '../bindings/account';

// Account's one owned module, Brand, sits in the Overview-equivalent 'details'
// slot. Connections is deliberately empty: the singleton has no relationships
// to project — DrawerTabs still renders the fixed two-tab bar (it is not
// configurable), but nothing is placed under it.
export const ACCOUNT_DRAWER_ENTITY: EntitySchema = {
  id: 'account',
  label: { singular: 'Account', plural: 'Account' },
  identity: {
    // The record IS the singleton, addressed by its fixed sentinel rather
    // than a numeric or string record id — there is only ever one.
    idOf: () => 'account',
    platformIdOf: (data) => data.nodes.profile.platformId ?? '',
    titleOf: (data) => (data.brand.name?.trim() || 'Account'),
  },
  lifecycle: {
    participation: 'canonical',
    // No Archive/Trash/Restore/permanent-delete for this singleton — an open
    // exception proposal awaiting an explicit Reviewer/Owner decision, not a
    // settled exemption (see src/Modules/Account/CLAUDE.md).
    statuses: ['active', 'disabled'],
  },
  shells: {
    brand: accountBrandShell,
  },
  // No entity travel actions: Account has no Archive/Trash/Restore/Delete.
  actions: {},
  placements: {
    drawer: {
      details: [{ module: 'brand', mode: 'details' }],
      connections: [],
    },
  },
};
