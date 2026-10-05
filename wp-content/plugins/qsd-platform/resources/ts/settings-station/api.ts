// Settings Station endpoint calls — the single implementation of the
// Settings-owned `qsd/v1/admin/settings/*` family. Maps wire snake_case to the
// application contracts in ./types; no secret value is ever read from a
// response, because the backend never sends one.

import { apiClient } from '@/api/client';
import type {
  ConnectionField,
  ConnectionProjection,
  ConnectionSavePayload,
  ServiceMetaField,
  ServiceMetaFieldDraft,
  ServiceMetaSubField,
} from './types';

interface WireConnection {
  provider: string;
  label: string;
  description: string;
  state: ConnectionProjection['state'];
  updated_at: string | null;
  fields: ConnectionField[];
}

interface WireField extends Omit<ServiceMetaField, 'subFields'> {
  sub_fields?: ServiceMetaSubField[];
}

function toConnection(wire: WireConnection): ConnectionProjection {
  return {
    provider:    wire.provider,
    label:       wire.label,
    description: wire.description,
    state:       wire.state,
    updatedAt:   wire.updated_at,
    // Only the declared safe keys are carried across, whatever the wire holds.
    fields: wire.fields.map((field) =>
      field.type === 'secret'
        ? { key: field.key, label: field.label, type: 'secret', required: field.required, configured: field.configured }
        : field,
    ),
  };
}

function toField(wire: WireField): ServiceMetaField {
  const { sub_fields, ...rest } = wire;
  return sub_fields ? { ...rest, subFields: sub_fields } : rest;
}

function toWireDraft(draft: Partial<ServiceMetaFieldDraft>): Record<string, unknown> {
  const { subFields, ...rest } = draft;
  return subFields ? { ...rest, sub_fields: subFields } : rest;
}

export async function fetchConnections(): Promise<ConnectionProjection[]> {
  const response = await apiClient.get<{ connections: WireConnection[] }>('admin/settings/connections');
  return response.connections.map(toConnection);
}

export async function saveConnection(provider: string, payload: ConnectionSavePayload): Promise<ConnectionProjection> {
  const response = await apiClient.put<{ connection: WireConnection }>(`admin/settings/connections/${provider}`, payload);
  return toConnection(response.connection);
}

export async function disconnectConnection(provider: string): Promise<ConnectionProjection> {
  const response = await apiClient.delete<{ connection: WireConnection }>(`admin/settings/connections/${provider}`);
  return toConnection(response.connection);
}

export async function fetchServiceMetaFields(): Promise<ServiceMetaField[]> {
  const response = await apiClient.get<{ fields: WireField[] }>('admin/settings/service-meta/fields');
  return response.fields.map(toField);
}

export async function createServiceMetaField(draft: ServiceMetaFieldDraft): Promise<ServiceMetaField> {
  const response = await apiClient.post<{ field: WireField }>('admin/settings/service-meta/fields', toWireDraft(draft));
  return toField(response.field);
}

export async function updateServiceMetaField(id: string, draft: Partial<ServiceMetaFieldDraft>): Promise<ServiceMetaField> {
  const response = await apiClient.put<{ field: WireField }>(`admin/settings/service-meta/fields/${id}`, toWireDraft(draft));
  return toField(response.field);
}

export async function reorderServiceMetaFields(ids: string[]): Promise<ServiceMetaField[]> {
  const response = await apiClient.post<{ fields: WireField[] }>('admin/settings/service-meta/fields/order', { ids });
  return response.fields.map(toField);
}

export async function retireServiceMetaField(id: string): Promise<ServiceMetaField> {
  const response = await apiClient.post<{ field: WireField }>(`admin/settings/service-meta/fields/${id}/retire`);
  return toField(response.field);
}

export async function restoreServiceMetaField(id: string): Promise<ServiceMetaField> {
  const response = await apiClient.post<{ field: WireField }>(`admin/settings/service-meta/fields/${id}/restore`);
  return toField(response.field);
}
