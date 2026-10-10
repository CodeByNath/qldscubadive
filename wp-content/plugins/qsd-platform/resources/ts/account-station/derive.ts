// Account Station — stateless pill/publish derivations. Pure projections of
// AccountDetail; no fetch, no mutation.

import type { AccountDetail } from './types';

export type AccountPillStatus = 'active' | 'pending-dim' | 'pending-full' | 'disabled';

/**
 * Lifecycle Contract pill vocabulary, derived from Account's own detail —
 * the drawer-kit renderer never infers it. 'disabled' means the explicit
 * Disable mask (previousPlatformStatus non-empty); an unmasked 'disabled'
 * (never published, or Enabled back to Pending) reads as Pending — dim
 * while the Brand draft is still empty, full once there is real content to
 * review.
 */
export function deriveAccountPillStatus(detail: AccountDetail): AccountPillStatus {
  if (detail.platformStatus === 'active') {
    return 'active';
  }
  if (detail.previousPlatformStatus !== '') {
    return 'disabled';
  }

  const hasContent = detail.hasDraft || detail.brand.name !== '' || detail.brand.code !== '';
  return hasContent ? 'pending-full' : 'pending-dim';
}

/** Brand has no required field — any bootstrapped Account may publish. */
export function canPublishAccount(detail: AccountDetail): boolean {
  return detail.bootstrapped;
}
