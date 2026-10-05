// Service Meta lane — the Settings-owned list of configurable Service field
// DEFINITIONS. Add, edit, reorder, retire and restore; there is no delete,
// because removing a definition could orphan stored Service values. Retire is
// armed in place with useInlineConfirm (contract §11).
//
// Each row shows the field's stable id: that id, not the label or position, is
// what Service values will be keyed by.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { useInlineConfirm } from '@/hooks/useInlineConfirm';
import { useServiceMetaSchema } from '../useServiceMetaSchema';
import type { ServiceMetaField } from '../types';
import { FIELD_TYPE_LABEL, ServiceMetaFieldEditor } from './ServiceMetaFieldEditor';

type Editing = { mode: 'new' } | { mode: 'edit'; id: string } | null;

function childSummary(field: ServiceMetaField): string | null {
  if (field.options) return `${field.options.length} option${field.options.length === 1 ? '' : 's'}`;
  if (field.subFields) return `${field.subFields.length} group field${field.subFields.length === 1 ? '' : 's'}`;
  return null;
}

export function ServiceMetaSchemaLane(): VNode {
  const schema = useServiceMetaSchema();
  const confirm = useInlineConfirm<string>();
  const [editing, setEditing] = useState<Editing>(null);

  if (schema.loading) return <p class="cz-station-empty">Loading Service Meta fields…</p>;
  if (schema.error) return <p class="cz-station-empty" role="alert">{schema.error}</p>;

  const close = () => setEditing(null);

  return (
    <div class="cz-settings-lane">
      <p class="cz-settings-muted">
        Define the descriptive fields every Service can carry. Settings owns these definitions; each Service's own values stay with the Service.
      </p>

      {schema.actionError && <p class="cz-settings-error" role="alert">{schema.actionError}</p>}

      {editing?.mode === 'new' ? (
        <ServiceMetaFieldEditor field={null} busy={schema.busy} onSave={schema.create} onCancel={close} />
      ) : (
        <div class="cz-settings-actions cz-settings-actions--start">
          <button type="button" class="cz-settings-button cz-settings-button--primary" onClick={() => setEditing({ mode: 'new' })} disabled={schema.busy}>
            Add field
          </button>
        </div>
      )}

      {schema.fields.length === 0 ? (
        <p class="cz-station-empty">No Service Meta fields yet.</p>
      ) : (
        <ul class="cz-settings-list">
          {schema.fields.map((field, index) => {
            const retired = field.status === 'retired';
            const summary = childSummary(field);
            if (editing?.mode === 'edit' && editing.id === field.id) {
              return (
                <li class="cz-settings-meta-row" key={field.id} data-field-id={field.id}>
                  <ServiceMetaFieldEditor field={field} busy={schema.busy} onSave={(draft) => schema.update(field.id, draft)} onCancel={close} />
                </li>
              );
            }
            return (
              <li class={retired ? 'cz-settings-meta-row cz-settings-meta-row--retired' : 'cz-settings-meta-row'} key={field.id} data-field-id={field.id}>
                <div class="cz-settings-meta-row__identity">
                  <span class="cz-settings-meta-row__label">{field.label}</span>
                  <span class="cz-settings-muted">
                    {FIELD_TYPE_LABEL[field.type]}{summary ? ` · ${summary}` : ''}{field.required ? ' · Required' : ''}
                  </span>
                  <code class="cz-settings-meta-row__id">{field.id}</code>
                </div>
                {retired && <span class="cz-settings-state">Retired</span>}
                <div class="cz-settings-actions">
                  {confirm.pendingId === field.id ? (
                    <span class="cz-settings-confirm">
                      <span class="cz-settings-confirm__prompt">Retire {field.label}? Stored values are kept.</span>
                      <button type="button" class="cz-settings-button" onClick={confirm.cancel} disabled={schema.busy}>Cancel</button>
                      <button type="button" class="cz-settings-button cz-settings-button--danger" disabled={schema.busy}
                        onClick={() => confirm.run(field.id, () => schema.retire(field.id))}>Retire</button>
                    </span>
                  ) : (
                    <>
                      <button type="button" class="cz-settings-button" aria-label={`Move ${field.label} up`} disabled={schema.busy || index === 0}
                        onClick={() => schema.move(field.id, -1)}>↑</button>
                      <button type="button" class="cz-settings-button" aria-label={`Move ${field.label} down`} disabled={schema.busy || index === schema.fields.length - 1}
                        onClick={() => schema.move(field.id, 1)}>↓</button>
                      <button type="button" class="cz-settings-button" disabled={schema.busy} onClick={() => setEditing({ mode: 'edit', id: field.id })}>Edit</button>
                      {retired ? (
                        <button type="button" class="cz-settings-button" disabled={schema.busy} onClick={() => schema.restore(field.id)}>Restore</button>
                      ) : (
                        <button type="button" class="cz-settings-button" disabled={schema.busy} onClick={() => confirm.request(field.id)}>Retire</button>
                      )}
                    </>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
