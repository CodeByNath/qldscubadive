/*
 * Account Station — the endpoint functions for the Account singleton.
 *
 * The authoritative implementation of every Account-owned call
 * (src/Modules/Account Http/AccountController). Wire responses are
 * snake_case; this file alone maps them to the camelCase AccountDetail
 * contract other modules consume.
 */

import { apiClient } from '@/api/client';
import type {
  AccountDetail,
  AccountMediaItem,
  AccountMediaKind,
  AccountProfileInput,
} from './types';

interface WireAccountNode {
  platform_id: string | null;
}

interface WireAccountDetail {
  bootstrapped: boolean;
  nodes: {
    account: WireAccountNode;
    settings: WireAccountNode;
    tools: WireAccountNode;
    profile: WireAccountNode;
  };
  platform_status: 'active' | 'disabled';
  previous_platform_status: string;
  module_status: { brand: 'not-configured' | 'pending' | 'settled' };
  brand: {
    name: string;
    code: string;
    logo_url: string | null;
    favicon_url: string | null;
  };
  has_draft: boolean;
}

interface WireAccountResponse {
  success: boolean;
  account: WireAccountDetail;
}

interface WireMediaItem {
  media_id: string;
  url: string;
  mime: string;
  size: number;
  created_at: string | null;
}

function mapAccountDetail(wire: WireAccountDetail): AccountDetail {
  return {
    bootstrapped: wire.bootstrapped,
    nodes: {
      account: { platformId: wire.nodes.account.platform_id },
      settings: { platformId: wire.nodes.settings.platform_id },
      tools: { platformId: wire.nodes.tools.platform_id },
      profile: { platformId: wire.nodes.profile.platform_id },
    },
    platformStatus: wire.platform_status,
    previousPlatformStatus: wire.previous_platform_status,
    moduleStatus: wire.module_status,
    brand: {
      name: wire.brand.name,
      code: wire.brand.code,
      logoUrl: wire.brand.logo_url,
      faviconUrl: wire.brand.favicon_url,
    },
    hasDraft: wire.has_draft,
  };
}

function mapMediaItem(wire: WireMediaItem): AccountMediaItem {
  return {
    mediaId: wire.media_id,
    url: wire.url,
    mime: wire.mime,
    size: wire.size,
    createdAt: wire.created_at,
  };
}

export async function fetchAccountDetail(): Promise<AccountDetail> {
  const response = await apiClient.get<WireAccountResponse>('admin/account');
  return mapAccountDetail(response.account);
}

export async function bootstrapAccount(): Promise<AccountDetail> {
  const response = await apiClient.post<WireAccountResponse>('admin/account/bootstrap');
  return mapAccountDetail(response.account);
}

export async function saveAccountProfile(input: AccountProfileInput): Promise<AccountDetail> {
  const payload: Record<string, unknown> = {};
  if (input.name !== undefined) payload.name = input.name;
  if (input.code !== undefined) payload.code = input.code;
  if (input.logoMediaId !== undefined) payload.logo_media_id = input.logoMediaId;
  if (input.faviconMediaId !== undefined) payload.favicon_media_id = input.faviconMediaId;

  const response = await apiClient.post<WireAccountResponse>('admin/account/profile', payload);
  return mapAccountDetail(response.account);
}

export async function settleAccountProfile(): Promise<AccountDetail> {
  const response = await apiClient.post<WireAccountResponse>('admin/account/profile/settle');
  return mapAccountDetail(response.account);
}

export async function publishAccount(): Promise<AccountDetail> {
  const response = await apiClient.post<WireAccountResponse>('admin/account/status', { platform_status: 'active' });
  return mapAccountDetail(response.account);
}

export async function disableAccount(): Promise<AccountDetail> {
  const response = await apiClient.post<WireAccountResponse>('admin/account/status', { action: 'disable' });
  return mapAccountDetail(response.account);
}

export async function enableAccount(): Promise<AccountDetail> {
  const response = await apiClient.post<WireAccountResponse>('admin/account/status', { action: 'enable' });
  return mapAccountDetail(response.account);
}

export async function listAccountMedia(): Promise<AccountMediaItem[]> {
  const response = await apiClient.get<{ success: boolean; media: WireMediaItem[] }>(
    'admin/account/profile/media/library',
  );
  return response.media.map(mapMediaItem);
}

// Multipart, not JSON — the only Account call apiClient cannot carry, since the
// backend reads a real $_FILES upload (WP_REST_Request::get_file_params()),
// not a JSON body. A dedicated fetch, same nonce/credentials contract as
// apiClient, no Content-Type header so the browser sets the multipart
// boundary itself.
export async function uploadAccountMedia(kind: AccountMediaKind, file: File): Promise<AccountMediaItem> {
  const config = window.QSDConfig;
  if (!config) {
    throw new Error('QSDConfig is not defined. Core\\AssetLoader writes it on the /station/ route.');
  }

  const body = new FormData();
  body.append('kind', kind);
  body.append('file', file);

  const url = config.apiRoot.replace(/\/$/, '') + '/admin/account/profile/media';
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-WP-Nonce': config.nonce },
    body,
  });

  if (!res.ok) {
    const text = await res.text().catch(() => res.statusText);
    throw new Error(`API POST admin/account/profile/media → ${res.status}: ${text}`);
  }

  const response = (await res.json()) as { success: boolean; media: WireMediaItem };
  return mapMediaItem(response.media);
}
