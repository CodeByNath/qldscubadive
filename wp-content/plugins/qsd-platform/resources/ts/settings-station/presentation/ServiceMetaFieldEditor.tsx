// Service Meta field editor — the inline add/edit form for one field
// definition. Pure presentation over a local draft; `onSave` is the hook's
// create/update handler.
//
// Identity rules mirrored from the backend so the form never offers what the
// server would refuse: an existing field's type is fixed; existing options and
// sub-fields keep their ids and can be relabelled or reordered but not removed
// (stored Service values may use them); only rows added in this edit can be
// removed again before saving.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import type {
  ServiceMetaField,
  ServiceMetaFieldDraft,
  ServiceMetaFieldType,
  ServiceMetaOptionDraft,
  ServiceMetaSubFieldDraft,
  ServiceMetaSubFieldType,
} from '../types';

export const FIELD_TYPE_LABEL: Record<ServiceMetaFieldType, string> = {
  text:     'Text',
  textarea: 'Long text',
  number:   'Number',
  boolean:  'Yes / No',
  select:   'Select',
  image:    'Image',
  gallery:  'Image gallery',
  repeater: 'Repeater group',
};

const SUB_FIELD_TYPES = Object.keys(FIELD_TYPE_LABEL).filter((type) => type !== 'repeater') as ServiceMetaSubFieldType[];

function toDraft(field: ServiceMetaField | null): ServiceMetaFieldDraft {
  if (!field) return { label: '', type: 'text', help: '', required: false };
  return {
    label: field.label,
    type: field.type,
    help: field.help,
    required: field.required,
    options: field.options?.map((o) => ({ id: o.id, label: o.label })),
    subFields: field.subFields?.map((s) => ({
      id: s.id, label: s.label, type: s.type, help: s.help, required: s.required,
      options: s.options?.map((o) => ({ id: o.id, label: o.label })),
    })),
  };
}

function move<T>(list: T[], index: number, direction: -1 | 1): T[] {
  const to = index + direction;
  if (to < 0 || to >= list.length) return list;
  const next = [...list];
  [next[index], next[to]] = [next[to], next[index]];
  return next;
}

function OptionsEditor({ options, onChange, idPrefix }: {
  options: ServiceMetaOptionDraft[];
  onChange: (next: ServiceMetaOptionDraft[]) => void;
  idPrefix: string;
}): VNode {
  return (
    <fieldset class="cz-settings-meta-editor__group">
      <legend class="cz-tf-label">Options</legend>
      {options.map((option, index) => (
        <div class="cz-settings-meta-editor__row" key={option.id ?? `new-${index}`}>
          <input
            type="text" class="cz-tf-control cz-tf-input" aria-label={`Option ${index + 1}`} id={`${idPrefix}-option-${index}`}
            value={option.label}
            onInput={(e) => onChange(options.map((o, i) => (i === index ? { ...o, label: (e.target as HTMLInputElement).value } : o)))}
          />
          <button type="button" class="cz-settings-button" aria-label="Move option up" onClick={() => onChange(move(options, index, -1))}>↑</button>
          <button type="button" class="cz-settings-button" aria-label="Move option down" onClick={() => onChange(move(options, index, 1))}>↓</button>
          {!option.id && (
            <button type="button" class="cz-settings-button" onClick={() => onChange(options.filter((_, i) => i !== index))}>Remove</button>
          )}
        </div>
      ))}
      <button type="button" class="cz-settings-link" onClick={() => onChange([...options, { label: '' }])}>Add option</button>
    </fieldset>
  );
}

function SubFieldsEditor({ subFields, onChange }: {
  subFields: ServiceMetaSubFieldDraft[];
  onChange: (next: ServiceMetaSubFieldDraft[]) => void;
}): VNode {
  const patch = (index: number, change: Partial<ServiceMetaSubFieldDraft>) =>
    onChange(subFields.map((s, i) => (i === index ? { ...s, ...change } : s)));

  return (
    <fieldset class="cz-settings-meta-editor__group">
      <legend class="cz-tf-label">Group fields</legend>
      {subFields.map((sub, index) => (
        <div class="cz-settings-meta-editor__sub" key={sub.id ?? `new-${index}`}>
          <div class="cz-settings-meta-editor__row">
            <input
              type="text" class="cz-tf-control cz-tf-input" aria-label={`Group field ${index + 1} label`}
              value={sub.label} onInput={(e) => patch(index, { label: (e.target as HTMLInputElement).value })}
            />
            <select
              class="cz-tf-control cz-tf-select" aria-label={`Group field ${index + 1} type`} value={sub.type} disabled={!!sub.id}
              onChange={(e) => {
                const type = (e.target as HTMLSelectElement).value as ServiceMetaSubFieldType;
                patch(index, { type, options: type === 'select' ? [{ label: '' }] : undefined });
              }}
            >
              {SUB_FIELD_TYPES.map((type) => <option key={type} value={type}>{FIELD_TYPE_LABEL[type]}</option>)}
            </select>
            <button type="button" class="cz-settings-button" aria-label="Move group field up" onClick={() => onChange(move(subFields, index, -1))}>↑</button>
            <button type="button" class="cz-settings-button" aria-label="Move group field down" onClick={() => onChange(move(subFields, index, 1))}>↓</button>
            {!sub.id && (
              <button type="button" class="cz-settings-button" onClick={() => onChange(subFields.filter((_, i) => i !== index))}>Remove</button>
            )}
          </div>
          {sub.type === 'select' && (
            <OptionsEditor idPrefix={`sub-${index}`} options={sub.options ?? []} onChange={(options) => patch(index, { options })} />
          )}
        </div>
      ))}
      <button type="button" class="cz-settings-link" onClick={() => onChange([...subFields, { label: '', type: 'text' }])}>Add group field</button>
    </fieldset>
  );
}

export function ServiceMetaFieldEditor({ field, busy, onSave, onCancel }: {
  field: ServiceMetaField | null;
  busy: boolean;
  onSave: (draft: ServiceMetaFieldDraft) => Promise<boolean>;
  onCancel: () => void;
}): VNode {
  const [draft, setDraft] = useState<ServiceMetaFieldDraft>(() => toDraft(field));
  const set = (change: Partial<ServiceMetaFieldDraft>) => setDraft((prev) => ({ ...prev, ...change }));
  const idPrefix = field ? `cz-meta-${field.id}` : 'cz-meta-new';

  const changeType = (type: ServiceMetaFieldType) => set({
    type,
    options: type === 'select' ? [{ label: '' }] : undefined,
    subFields: type === 'repeater' ? [{ label: '', type: 'text' }] : undefined,
  });

  return (
    <form
      class="cz-settings-meta-editor"
      aria-label={field ? `Edit ${field.label}` : 'New Service Meta field'}
      onSubmit={async (e) => { e.preventDefault(); if (await onSave(draft)) onCancel(); }}
    >
      <div class="cz-tf-field">
        <label class="cz-tf-label cz-tf-label--required" for={`${idPrefix}-label`}>Label</label>
        <input id={`${idPrefix}-label`} type="text" class="cz-tf-control cz-tf-input" maxLength={80} value={draft.label}
          onInput={(e) => set({ label: (e.target as HTMLInputElement).value })} />
      </div>
      <div class="cz-tf-field">
        <label class="cz-tf-label" for={`${idPrefix}-type`}>Type</label>
        <select id={`${idPrefix}-type`} class="cz-tf-control cz-tf-select" value={draft.type} disabled={!!field}
          onChange={(e) => changeType((e.target as HTMLSelectElement).value as ServiceMetaFieldType)}>
          {(Object.keys(FIELD_TYPE_LABEL) as ServiceMetaFieldType[]).map((type) => (
            <option key={type} value={type}>{FIELD_TYPE_LABEL[type]}</option>
          ))}
        </select>
        {field && <span class="cz-tf-hint">A field's type is fixed once created.</span>}
      </div>
      <div class="cz-tf-field">
        <label class="cz-tf-label" for={`${idPrefix}-help`}>Help text</label>
        <textarea id={`${idPrefix}-help`} class="cz-tf-control cz-tf-textarea" value={draft.help ?? ''}
          onInput={(e) => set({ help: (e.target as HTMLTextAreaElement).value })} />
      </div>
      <label class="cz-tf-field__inline">
        <input type="checkbox" class="cz-tf-checkbox" checked={!!draft.required}
          onChange={(e) => set({ required: (e.target as HTMLInputElement).checked })} />
        <span>Required</span>
      </label>

      {draft.type === 'select' && (
        <OptionsEditor idPrefix={idPrefix} options={draft.options ?? []} onChange={(options) => set({ options })} />
      )}
      {draft.type === 'repeater' && (
        <SubFieldsEditor subFields={draft.subFields ?? []} onChange={(subFields) => set({ subFields })} />
      )}

      <div class="cz-settings-actions">
        <button type="button" class="cz-settings-button" onClick={onCancel} disabled={busy}>Cancel</button>
        <button type="submit" class="cz-settings-button cz-settings-button--primary" disabled={busy || draft.label.trim() === ''}>
          {busy ? 'Saving…' : field ? 'Save field' : 'Add field'}
        </button>
      </div>
    </form>
  );
}
