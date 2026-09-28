import { apiClient } from '../client';
import type {
  CategoryDeleteResponse,
  CategoryListResponse,
  CategoryMutationResponse,
  CategoryOverviewDraft,
  CategoryOverviewSaveResponse,
} from '../types/admin';

type WirePlatformId<T extends { platformId: string }> = Omit<T, 'platformId'> & { platform_id: string };
type WireCategoryListResponse = Omit<CategoryListResponse, 'categories'> & {
  categories: Array<WirePlatformId<CategoryListResponse['categories'][number]>>;
};
type WireCategoryMutationResponse = Omit<CategoryMutationResponse, 'category'> & {
  category: WirePlatformId<CategoryMutationResponse['category']>;
};
type WireCategoryDeleteResponse = Omit<CategoryDeleteResponse, 'platformId'> & { platform_id: string };
type InlineCategory = { id: number; platformId: string; name: string; slug: string; description: string };
type WireInlineCategory = WirePlatformId<InlineCategory>;

function mapPlatformId<T extends { platformId: string }>(value: WirePlatformId<T>): T {
  const { platform_id, ...rest } = value;
  return { ...rest, platformId: platform_id } as unknown as T;
}

function mapCategoryMutation(response: WireCategoryMutationResponse): CategoryMutationResponse {
  return { ...response, category: mapPlatformId(response.category) };
}

// Service endpoint functions are owned by the Service Station and are NOT
// re-exported here. Import them from '@/service-station'.


// Service category inline creation.
export async function createServiceCategory(payload: {
  name:         string;
  description?: string;
}): Promise<{
  success:   boolean;
  existing?: boolean;
  message?:  string;
  category?: InlineCategory;
}> {
  const response = await apiClient.post<{
    success: boolean; existing?: boolean; message?: string; category?: WireInlineCategory;
  }>('admin/service-categories', payload);
  const { category, ...rest } = response;
  return category
    ? { ...rest, category: mapPlatformId<InlineCategory>(category) }
    : rest;
}

// Service category inline update (name and/or description).
export async function updateServiceCategory(
  id:      number,
  payload: { name?: string; description?: string },
): Promise<{
  success:   boolean;
  message?:  string;
  category?: InlineCategory;
}> {
  const response = await apiClient.post<{
    success: boolean; message?: string; category?: WireInlineCategory;
  }>(`admin/service-categories/${id}`, payload);
  const { category, ...rest } = response;
  return category
    ? { ...rest, category: mapPlatformId<InlineCategory>(category) }
    : rest;
}

// ── Category station (S6) ─────────────────────────────────────────────────────
// The /admin/categories family — additive beside the inline
// /admin/service-categories convenience routes above, whose behavior remains D3.

export async function fetchAdminCategories(platformStatus?: 'archived' | 'trashed'): Promise<CategoryListResponse> {
  const path = platformStatus
    ? `admin/categories?platform_status=${platformStatus}`
    : 'admin/categories';
  const response = await apiClient.get<WireCategoryListResponse>(path);
  return { ...response, categories: response.categories.map(mapPlatformId) };
}

// Station create: born as an unmasked Pending record with its Overview draft.
// Duplicate names fail (no return-existing convenience).
export async function createCategory(payload: {
  name:         string;
  description?: string;
}): Promise<CategoryMutationResponse> {
  const response = await apiClient.post<WireCategoryMutationResponse>('admin/categories', payload);
  return mapCategoryMutation(response);
}

// Save the overview draft — canonical term untouched, overview marked pending.
export function saveCategoryOverview(
  categoryId: number,
  payload:    CategoryOverviewDraft,
): Promise<CategoryOverviewSaveResponse> {
  return apiClient.put<CategoryOverviewSaveResponse>(`admin/categories/${categoryId}/overview`, payload);
}

// Commit the draft to the term (name + description), clear it, re-derive status.
export async function settleCategoryOverview(categoryId: number): Promise<CategoryMutationResponse> {
  const response = await apiClient.post<WireCategoryMutationResponse>(`admin/categories/${categoryId}/overview/settle`, {});
  return mapCategoryMutation(response);
}

// Discard the draft; module_status re-derives from the settled state.
export async function revertCategoryOverview(categoryId: number): Promise<CategoryMutationResponse> {
  const response = await apiClient.post<WireCategoryMutationResponse>(`admin/categories/${categoryId}/overview/revert`, {});
  return mapCategoryMutation(response);
}

// Engine transition — the only status write for categories.
export async function updateCategoryStatus(
  categoryId:     number,
  platformStatus: 'active' | 'disabled' | 'archived' | 'trashed',
): Promise<CategoryMutationResponse> {
  const response = await apiClient.patch<WireCategoryMutationResponse>(`admin/categories/${categoryId}/status`, {
    platform_status: platformStatus,
  });
  return mapCategoryMutation(response);
}

// Disable/Enable are an explicit presentation mask, distinct from the
// platform_status shape Publish, Archive, and Trash use.
export async function disableCategory(categoryId: number): Promise<CategoryMutationResponse> {
  const response = await apiClient.patch<WireCategoryMutationResponse>(`admin/categories/${categoryId}/status`, { action: 'disable' });
  return mapCategoryMutation(response);
}

export async function enableCategory(categoryId: number): Promise<CategoryMutationResponse> {
  const response = await apiClient.patch<WireCategoryMutationResponse>(`admin/categories/${categoryId}/status`, { action: 'enable' });
  return mapCategoryMutation(response);
}

// Server-driven restore — resolves previous_platform_status, lands disabled.
export async function restoreCategory(categoryId: number): Promise<CategoryMutationResponse> {
  const response = await apiClient.post<WireCategoryMutationResponse>(`admin/categories/${categoryId}/restore`, {});
  return mapCategoryMutation(response);
}

// Trashed-only. A D6 guard failure is an HTTP 409 (apiClient throws; the error
// text carries { message, assigned_count }).
export async function permanentDeleteCategory(categoryId: number): Promise<CategoryDeleteResponse> {
  const response = await apiClient.delete<WireCategoryDeleteResponse>(`admin/categories/${categoryId}`);
  const { platform_id, ...rest } = response;
  return { ...rest, platformId: platform_id };
}
