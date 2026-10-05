// Service Meta schema state — the Settings-owned field definitions and their
// create/update/reorder/retire/restore actions. Presentation calls these
// handlers, never ./api. Reorder sends ids only; identity never moves.

import { useCallback, useEffect, useState } from 'preact/hooks';
import {
  createServiceMetaField,
  fetchServiceMetaFields,
  reorderServiceMetaFields,
  restoreServiceMetaField,
  retireServiceMetaField,
  updateServiceMetaField,
} from './api';
import { errorMessage } from './errorMessage';
import type { ServiceMetaField, ServiceMetaFieldDraft } from './types';

export interface ServiceMetaSchemaState {
  fields:      ServiceMetaField[];
  loading:     boolean;
  error:       string | null;
  busy:        boolean;
  actionError: string | null;
  create:      (draft: ServiceMetaFieldDraft) => Promise<boolean>;
  update:      (id: string, draft: Partial<ServiceMetaFieldDraft>) => Promise<boolean>;
  move:        (id: string, direction: -1 | 1) => Promise<boolean>;
  retire:      (id: string) => Promise<boolean>;
  restore:     (id: string) => Promise<boolean>;
}

export function useServiceMetaSchema(): ServiceMetaSchemaState {
  const [fields, setFields] = useState<ServiceMetaField[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    fetchServiceMetaFields()
      .then(setFields)
      .catch((err: unknown) => setError(errorMessage(err, 'Could not load Service Meta fields.')))
      .finally(() => setLoading(false));
  }, []);

  const run = useCallback(async (operation: () => Promise<void>, fallback: string) => {
    setBusy(true);
    setActionError(null);
    try {
      await operation();
      return true;
    } catch (err) {
      setActionError(errorMessage(err, fallback));
      return false;
    } finally {
      setBusy(false);
    }
  }, []);

  const replace = (next: ServiceMetaField) =>
    setFields((prev) => prev.map((field) => (field.id === next.id ? next : field)));

  const create = useCallback((draft: ServiceMetaFieldDraft) => run(async () => {
    const field = await createServiceMetaField(draft);
    setFields((prev) => [...prev, field]);
  }, 'The field could not be created.'), [run]);

  const update = useCallback((id: string, draft: Partial<ServiceMetaFieldDraft>) => run(async () => {
    replace(await updateServiceMetaField(id, draft));
  }, 'The field could not be saved.'), [run]);

  const move = useCallback((id: string, direction: -1 | 1) => run(async () => {
    const ids = fields.map((field) => field.id);
    const from = ids.indexOf(id);
    const to = from + direction;
    if (from < 0 || to < 0 || to >= ids.length) return;
    [ids[from], ids[to]] = [ids[to], ids[from]];
    setFields(await reorderServiceMetaFields(ids));
  }, 'The fields could not be reordered.'), [run, fields]);

  const retire = useCallback((id: string) => run(async () => {
    replace(await retireServiceMetaField(id));
  }, 'The field could not be retired.'), [run]);

  const restore = useCallback((id: string) => run(async () => {
    replace(await restoreServiceMetaField(id));
  }, 'The field could not be restored.'), [run]);

  return { fields, loading, error, busy, actionError, create, update, move, retire, restore };
}
