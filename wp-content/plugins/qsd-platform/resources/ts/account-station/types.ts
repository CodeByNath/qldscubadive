// Account Station contracts — zero-import, mirrors the backend projection in
// Http/AccountController::projection(). Phase C: identity + Brand only. No
// Archive/Trash/Delete shape exists here, and none should be added without a
// separate, explicit Owner decision — Account is a singleton.

export interface AccountNode {
  platformId: string | null;
}

export type AccountModuleStatus = 'not-configured' | 'pending' | 'settled';
export type AccountPlatformStatus = 'active' | 'disabled';

export interface AccountBrand {
  name: string;
  code: string;
  logoUrl: string | null;
  faviconUrl: string | null;
}

export interface AccountDetail {
  bootstrapped: boolean;
  nodes: {
    account: AccountNode;
    settings: AccountNode;
    tools: AccountNode;
    profile: AccountNode;
  };
  platformStatus: AccountPlatformStatus;
  previousPlatformStatus: string;
  moduleStatus: { brand: AccountModuleStatus };
  brand: AccountBrand;
  hasDraft: boolean;
}

/** Partial — only the fields the editor actually changed are sent. */
export interface AccountProfileInput {
  name?: string;
  code?: string;
  logoMediaId?: string | null;
  faviconMediaId?: string | null;
}

export interface AccountMediaItem {
  mediaId: string;
  url: string;
  mime: string;
  size: number;
  createdAt: string | null;
}

export type AccountMediaKind = 'logo' | 'favicon';
