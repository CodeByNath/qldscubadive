// Settings Station public barrel — contracts only. Other peers consume
// Settings through these types; `register.ts` is entry-only and never exported.

export type {
  ConnectionField,
  ConnectionProjection,
  ConnectionState,
  ServiceMetaField,
  ServiceMetaFieldType,
  ServiceMetaOption,
  ServiceMetaSubField,
  ServiceMetaSubFieldType,
} from './types';
