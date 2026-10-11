// Account module rules — Account Station's single owned module, Brand.
//
// Brand has no required field (AccountBrand::applyDraft/settle always
// succeed), so completeness is never about field content. Dim vs full
// Pending instead follows whether there is anything to review yet: a saved
// draft, or a name/code already on the canonical record. This mirrors
// account-station/derive.ts's deriveAccountPillStatus exactly, so the Home
// card (which still reads through that function directly) and the drawer
// (which now reads through this module via evaluateModule) never disagree.

import type { ModuleDefinition, ModuleNote } from './shared';

export interface AccountBrandLike {
  bootstrapped: boolean;
  hasDraft: boolean;
  name: string;
  code: string;
}

export const accountBrandModule: ModuleDefinition<AccountBrandLike> = {
  key: 'account-brand',
  emptyPrompt: 'Edit to name this Account and add a Brand code.',
  isEmpty: (data) => !data.hasDraft && !data.name && !data.code,
  problems: (): ModuleNote[] => [],
  resolveStatus: (data, ctx) => {
    if (!data.hasDraft && !data.name && !data.code) return 'pending-dim';
    if (ctx.moduleTransition === 'pending') return 'pending-full';
    return ctx.platformStatus === 'active' ? 'active' : 'pending-full';
  },
};
