// Service Elements editor — edits this Service's Element INSTANCES against the
// Settings-owned definitions, inside the Service drawer's ordinary module
// editor (InlineEditorShell Save/Cancel). Presentation only: it reshapes the
// draft collection and never calls an endpoint.
//
// Identity is the server's: a new instance, Repeater row or gallery entry is
// added WITHOUT an id and receives its Service-child id on Save. Removing a
// saved instance marks it `detached` (kept, restorable) — never deleted; only
// an unsaved one simply disappears. Instances of a retired definition are shown
// read-only and sent back untouched.

import { useState } from 'preact/hooks';
import type {
  ElementsDraft,
  ServiceElement,
  ServiceElementDefinition,
  ServiceElementRow,
  ServiceGalleryEntry,
} from '@/service-station';

interface Props {
  draft:       ElementsDraft;
  definitions: ServiceElementDefinition[];
  onChange:    (next: ElementsDraft) => void;
}

// ── Pure draft helpers ───────────────────────────────────────────────────────

function emptyInstance(definition: ServiceElementDefinition): ServiceElement {
  const base: ServiceElement = { definition_id: definition.id, status: 'active' };
  if (definition.type === 'gallery') return { ...base, entries: [] };
  if (definition.type === 'group') return { ...base, children: [] };
  if (definition.type === 'repeater') return { ...base, rows: [] };
  return { ...base, value: null };
}

/** Swap with the nearest neighbour the editor actually shows (all, by default). */
function move<T>(items: T[], index: number, delta: -1 | 1, shown: (item: T) => boolean = () => true): T[] {
  let target = index + delta;
  while (target >= 0 && target < items.length && !shown(items[target])) target += delta;
  if (target < 0 || target >= items.length) return items;
  const next = items.slice();
  [next[index], next[target]] = [next[target], next[index]];
  return next;
}

/** Saved → detached (kept server-side); unsaved → dropped. */
function detach<T extends { id?: string; status: string }>(items: T[], index: number): T[] {
  return items[index].id
    ? items.map((item, i) => (i === index ? { ...item, status: 'detached' } : item))
    : items.filter((_, i) => i !== index);
}

function restore<T extends { status: string }>(items: T[], index: number): T[] {
  return items.map((item, i) => (i === index ? { ...item, status: 'active' } : item));
}

/** The active child for one sub-field definition, if any. */
function childFor(children: ServiceElement[], sub: ServiceElementDefinition): ServiceElement | undefined {
  return children.find((child) => child.definition_id === sub.id && child.status === 'active');
}

function withChild(children: ServiceElement[], sub: ServiceElementDefinition, next: ServiceElement): ServiceElement[] {
  const index = children.findIndex((child) => child.definition_id === sub.id && child.status === 'active');
  return index === -1 ? [...children, next] : children.map((child, i) => (i === index ? next : child));
}

/** Client-side check run before Save; the server validates authoritatively. */
export function elementsDraftError(draft: ElementsDraft): string | null {
  const badEntry = (element: ServiceElement): boolean =>
    (element.entries ?? []).some((entry) => entry.status === 'active' && !(entry.attachment > 0)) ||
    (element.children ?? []).some(badEntry) ||
    (element.rows ?? []).some((row) => row.children.some(badEntry));
  return draft.items.some(badEntry) ? 'Each gallery image needs a media attachment ID.' : null;
}

const inputValue = (event: Event): string => (event.target as HTMLInputElement).value;

// ── Value inputs ─────────────────────────────────────────────────────────────

function ScalarInput({ definition, element, onChange }: {
  definition: ServiceElementDefinition;
  element:    ServiceElement;
  onChange:   (next: ServiceElement) => void;
}) {
  const set = (value: ServiceElement['value']) => onChange({ ...element, value });
  const value = element.value;

  switch (definition.type) {
    case 'textarea':
      return (
        <textarea class="cz-tf-control cz-tf-textarea" aria-label={definition.label}
          value={typeof value === 'string' ? value : ''}
          onInput={(e) => set((e.target as HTMLTextAreaElement).value)} />
      );
    case 'number':
      return (
        <input type="number" class="cz-tf-control cz-tf-input" aria-label={definition.label}
          value={typeof value === 'number' ? String(value) : ''}
          onInput={(e) => { const raw = inputValue(e); set(raw === '' ? null : Number(raw)); }} />
      );
    case 'boolean':
      return (
        <label class="cz-tf-field__inline">
          <input type="checkbox" class="cz-tf-checkbox" checked={value === true}
            onChange={(e) => set((e.target as HTMLInputElement).checked)} />
          <span class="cz-tf-label">{definition.label}</span>
        </label>
      );
    case 'select':
      return (
        <select class="cz-tf-control cz-tf-select" aria-label={definition.label}
          value={typeof value === 'string' ? value : ''}
          onChange={(e) => set(inputValue(e) || null)}>
          <option value="">Not set</option>
          {(definition.options ?? []).map((option) => (
            <option key={option.id} value={option.id}>{option.label}</option>
          ))}
        </select>
      );
    case 'image':
      return (
        <input type="number" min={1} class="cz-tf-control cz-tf-input" placeholder="Media attachment ID"
          aria-label={`${definition.label} attachment ID`}
          value={typeof value === 'number' ? String(value) : ''}
          onInput={(e) => { const raw = inputValue(e); set(raw === '' ? null : Number(raw)); }} />
      );
    default:
      return (
        <input type="text" class="cz-tf-control cz-tf-input" aria-label={definition.label}
          value={typeof value === 'string' ? value : ''}
          onInput={(e) => set(inputValue(e))} />
      );
  }
}

function GalleryInput({ definition, element, onChange }: {
  definition: ServiceElementDefinition;
  element:    ServiceElement;
  onChange:   (next: ServiceElement) => void;
}) {
  const entries = element.entries ?? [];
  const setEntries = (next: ServiceGalleryEntry[]) => onChange({ ...element, entries: next });

  return (
    <div class="cz-ie-list">
      {entries.map((entry, index) => entry.status === 'active' ? (
        <div class="cz-ie-row" key={entry.id ?? `new-entry-${index}`} data-entry-id={entry.id ?? ''}>
          <input type="number" min={1} class="cz-tf-control cz-tf-input" placeholder="Media attachment ID"
            aria-label={`${definition.label} image ${index + 1} attachment ID`}
            value={entry.attachment > 0 ? String(entry.attachment) : ''}
            onInput={(e) => setEntries(entries.map((item, i) => (i === index ? { ...item, attachment: Number(inputValue(e)) || 0 } : item)))} />
          <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Move image up"
            onClick={() => setEntries(move(entries, index, -1))}>↑</button>
          <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Move image down"
            onClick={() => setEntries(move(entries, index, 1))}>↓</button>
          <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Remove image"
            onClick={() => setEntries(detach(entries, index))}>✕</button>
        </div>
      ) : (
        <div class="cz-ie-row" key={entry.id} data-entry-id={entry.id}>
          <span class="cz-tf-hint">Removed image #{entry.attachment}</span>
          <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm"
            onClick={() => setEntries(restore(entries, index))}>Restore</button>
        </div>
      ))}
      <button type="button" class="cz-tf-add-btn"
        onClick={() => setEntries([...entries, { status: 'active', attachment: 0 }])}>+ Add image</button>
    </div>
  );
}

function ChildFields({ definition, children, onChange }: {
  definition: ServiceElementDefinition;
  children:   ServiceElement[];
  onChange:   (next: ServiceElement[]) => void;
}) {
  return (
    <>
      {(definition.sub_fields ?? []).map((sub) => {
        const child = childFor(children, sub) ?? emptyInstance(sub);
        return (
          <div class="cz-tf-field" key={sub.id}>
            {sub.type !== 'boolean' && <span class="cz-tf-label">{sub.label}</span>}
            <ValueInput definition={sub} element={child} onChange={(next) => onChange(withChild(children, sub, next))} />
          </div>
        );
      })}
    </>
  );
}

function RepeaterInput({ definition, element, onChange }: {
  definition: ServiceElementDefinition;
  element:    ServiceElement;
  onChange:   (next: ServiceElement) => void;
}) {
  const rows = element.rows ?? [];
  const setRows = (next: ServiceElementRow[]) => onChange({ ...element, rows: next });
  let shown = 0;

  return (
    <div class="cz-ie-list">
      {rows.map((row, index) => {
        if (row.status !== 'active') {
          return (
            <div class="cz-ie-row" key={row.id} data-row-id={row.id}>
              <span class="cz-tf-hint">Removed row</span>
              <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm"
                onClick={() => setRows(restore(rows, index))}>Restore</button>
            </div>
          );
        }
        shown += 1;
        return (
          <div class="cz-ie-faq-item" key={row.id ?? `new-row-${index}`} data-row-id={row.id ?? ''}>
            <div class="cz-ie-faq-item__header">
              <span class="cz-tf-label">Row {shown}</span>
              <span>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Move row up"
                  onClick={() => setRows(move(rows, index, -1))}>↑</button>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Move row down"
                  onClick={() => setRows(move(rows, index, 1))}>↓</button>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label="Remove row"
                  onClick={() => setRows(detach(rows, index))}>✕</button>
              </span>
            </div>
            <ChildFields definition={definition} children={row.children}
              onChange={(children) => setRows(rows.map((item, i) => (i === index ? { ...item, children } : item)))} />
          </div>
        );
      })}
      <button type="button" class="cz-tf-add-btn"
        onClick={() => setRows([...rows, { status: 'active', children: [] }])}>+ Add row</button>
    </div>
  );
}

function ValueInput(props: {
  definition: ServiceElementDefinition;
  element:    ServiceElement;
  onChange:   (next: ServiceElement) => void;
}) {
  const { definition, element, onChange } = props;
  if (definition.type === 'gallery') return <GalleryInput {...props} />;
  if (definition.type === 'repeater') return <RepeaterInput {...props} />;
  if (definition.type === 'group') {
    return <ChildFields definition={definition} children={element.children ?? []}
      onChange={(children) => onChange({ ...element, children })} />;
  }
  return <ScalarInput {...props} />;
}

// ── Editor ───────────────────────────────────────────────────────────────────

export function ServiceElementsEditor({ draft, definitions, onChange }: Props) {
  const [adding, setAdding] = useState('');
  const items = draft.items;
  const definitionOf = (element: ServiceElement) => definitions.find((field) => field.id === element.definition_id);
  const editable = (element: ServiceElement) => definitionOf(element)?.status === 'active';
  // Detached instances of active fields are hidden (re-adding the field revives them).
  const shown = (element: ServiceElement) => !editable(element) || element.status === 'active';
  const setItems = (next: ServiceElement[]) => onChange({ items: next });

  const addable = definitions.filter((field) =>
    field.status === 'active' &&
    !items.some((item) => item.definition_id === field.id && item.status === 'active'));

  const add = () => {
    const definition = definitions.find((field) => field.id === adding);
    if (!definition) return;
    const detachedIndex = items.findIndex((item) => item.definition_id === definition.id && item.status === 'detached');
    // Re-adding a removed field brings back the SAME instance (its id and values).
    setItems(detachedIndex !== -1 ? restore(items, detachedIndex) : [...items, emptyInstance(definition)]);
    setAdding('');
  };

  return (
    <div class="cz-tf-form">
      {definitions.length === 0 && (
        <p class="cz-tf-hint">No elements are defined yet. Define them in Settings → Service Meta.</p>
      )}

      {items.map((element, index) => {
        const definition = definitionOf(element);
        const key = element.id ?? `new-${element.definition_id}`;
        if (!definition || !editable(element)) {
          return (
            <div class="cz-ie-faq-item" key={key} data-element-id={element.id ?? ''}>
              <div class="cz-ie-faq-item__header">
                <span class="cz-tf-label">{definition ? `${definition.label} (retired field)` : 'Unknown field'}</span>
              </div>
              <p class="cz-tf-hint">Kept read-only. Restore the field in Settings to edit it again.</p>
            </div>
          );
        }
        if (element.status !== 'active') return null;
        return (
          <div class="cz-ie-faq-item" key={key} data-element-id={element.id ?? ''}>
            <div class="cz-ie-faq-item__header">
              <span class={definition.required ? 'cz-tf-label cz-tf-label--required' : 'cz-tf-label'}>{definition.label}</span>
              <span>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label={`Move ${definition.label} up`}
                  onClick={() => setItems(move(items, index, -1, shown))}>↑</button>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label={`Move ${definition.label} down`}
                  onClick={() => setItems(move(items, index, 1, shown))}>↓</button>
                <button type="button" class="cz-admin-btn cz-admin-btn--secondary cz-admin-btn--sm" aria-label={`Remove ${definition.label}`}
                  onClick={() => setItems(detach(items, index))}>✕</button>
              </span>
            </div>
            {definition.help && <p class="cz-tf-hint">{definition.help}</p>}
            <ValueInput definition={definition} element={element}
              onChange={(next) => setItems(items.map((item, i) => (i === index ? next : item)))} />
          </div>
        );
      })}

      {addable.length > 0 && (
        <div class="cz-ie-row">
          <select class="cz-tf-control cz-tf-select" aria-label="Element to add" value={adding}
            onChange={(e) => setAdding(inputValue(e))}>
            <option value="">Choose an element…</option>
            {addable.map((field) => <option key={field.id} value={field.id}>{field.label}</option>)}
          </select>
          <button type="button" class="cz-admin-btn cz-admin-btn--primary cz-admin-btn--sm" disabled={!adding} onClick={add}>
            Add
          </button>
        </div>
      )}
    </div>
  );
}
