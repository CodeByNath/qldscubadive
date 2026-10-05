// Settings Station contracts — zero-import, cycle-safe.
//
// Connections: the safe projection the backend returns. A secret field carries
// only `configured`; there is deliberately no property that could hold a stored
// secret value. Secret input travels one way, in `ConnectionSavePayload`.
//
// Service Meta: field DEFINITIONS owned by Settings. `id` is the stable
// server-minted identity (`fld_…` / `opt_…`); labels and array order are never
// identity. Per-Service values are Service-owned and are not modelled here.

export type ConnectionState = 'not_configured' | 'incomplete' | 'configured';

export interface ConnectionTextField {
  key: string;
  label: string;
  type: 'text';
  required: boolean;
  value: string;
}

export interface ConnectionSelectField {
  key: string;
  label: string;
  type: 'select';
  required: boolean;
  value: string;
  options: { value: string; label: string }[];
}

export interface ConnectionSecretField {
  key: string;
  label: string;
  type: 'secret';
  required: boolean;
  configured: boolean;
}

export type ConnectionField = ConnectionTextField | ConnectionSelectField | ConnectionSecretField;

export interface ConnectionProjection {
  provider: string;
  label: string;
  description: string;
  state: ConnectionState;
  updatedAt: string | null;
  fields: ConnectionField[];
}

export interface ConnectionSavePayload {
  values?: Record<string, string>;
  secrets?: Record<string, string>;
  clear?: string[];
}

export type ServiceMetaFieldType =
  | 'text' | 'textarea' | 'number' | 'boolean' | 'select' | 'image' | 'gallery' | 'group' | 'repeater';

/** Containers (group, repeater) carry sub-fields of any non-container type. */
export type ServiceMetaSubFieldType = Exclude<ServiceMetaFieldType, 'group' | 'repeater'>;

export interface ServiceMetaOption {
  id: string;
  label: string;
}

export interface ServiceMetaSubField {
  id: string;
  label: string;
  type: ServiceMetaSubFieldType;
  help: string;
  required: boolean;
  options?: ServiceMetaOption[];
}

export interface ServiceMetaField {
  id: string;
  label: string;
  type: ServiceMetaFieldType;
  help: string;
  required: boolean;
  status: 'active' | 'retired';
  options?: ServiceMetaOption[];
  subFields?: ServiceMetaSubField[];
}

// Draft shapes sent to the backend. A child without `id` is new and gets one
// minted server-side; a child with `id` must already exist.
export interface ServiceMetaOptionDraft { id?: string; label: string }
export interface ServiceMetaSubFieldDraft {
  id?: string;
  label: string;
  type: ServiceMetaSubFieldType;
  help?: string;
  required?: boolean;
  options?: ServiceMetaOptionDraft[];
}
export interface ServiceMetaFieldDraft {
  label: string;
  type: ServiceMetaFieldType;
  help?: string;
  required?: boolean;
  options?: ServiceMetaOptionDraft[];
  subFields?: ServiceMetaSubFieldDraft[];
}
