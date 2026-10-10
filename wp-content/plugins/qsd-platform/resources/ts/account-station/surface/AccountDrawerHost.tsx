// Account drawer host — the Admin-Station adapter implementing DrawerContent
// directly. Account has exactly one module (Brand) and no Overview/
// Connections split, so this file owns the whole composition rather than
// going through the generic multi-module EntityDrawer/schema system built
// for Service's three-module structure. It is a separate instance from the
// Home card's useAccountProfileCard (the same two-instance rule Service
// keeps): a save here refreshes via `onSaved`, never the card's own hook.
//
// No Archive/Trash/Restore/Delete action exists in this footer and none
// should be added without a separate, explicit Owner decision — Account is
// a singleton carve-out from the Station/Drawer lifecycle contract's travel
// table (see src/Modules/Account/CLAUDE.md).

import { useEffect, useMemo, useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { ReadBlock } from '@/drawer-kit/ReadBlock';
import { InlineEditorShell } from '@/drawer-kit/InlineEditorShell';
import { EntityActionFooter } from '@/drawer-kit/EntityActionFooter';
import type { DrawerContentProps } from '@/station-manager/drawerTypes';
import { useAccountStation } from '../useAccountStation';
import { deriveAccountPillStatus, canPublishAccount } from '../derive';
import { AccountBrandEditor } from '../drawer/AccountBrandEditor';
import type { AccountMediaKind } from '../types';

interface BrandDraft {
  name: string;
  code: string;
}

export function AccountDrawerHost({ mode, onSaved, setFooter, setCloseGuard }: DrawerContentProps): VNode {
  const { detail, loading, error, busy, saveBrand, publish, disable, enable, uploadMedia } = useAccountStation();

  const [editing, setEditing] = useState(mode === 'edit');
  const [draft, setDraft] = useState<BrandDraft>({ name: '', code: '' });
  const [pendingLogoMediaId, setPendingLogoMediaId] = useState<string | undefined>(undefined);
  const [pendingLogoUrl, setPendingLogoUrl] = useState<string | null>(null);
  const [pendingFaviconMediaId, setPendingFaviconMediaId] = useState<string | undefined>(undefined);
  const [pendingFaviconUrl, setPendingFaviconUrl] = useState<string | null>(null);
  const [uploadingKind, setUploadingKind] = useState<AccountMediaKind | null>(null);
  const [splitOpen, setSplitOpen] = useState(false);

  const startEditing = () => {
    if (!detail) return;
    setDraft({ name: detail.brand.name, code: detail.brand.code });
    setPendingLogoMediaId(undefined);
    setPendingLogoUrl(null);
    setPendingFaviconMediaId(undefined);
    setPendingFaviconUrl(null);
    setEditing(true);
  };

  const isDirty = editing
    && !!detail
    && (draft.name !== detail.brand.name
      || draft.code !== detail.brand.code
      || pendingLogoMediaId !== undefined
      || pendingFaviconMediaId !== undefined);

  useEffect(() => {
    if (!setCloseGuard) return;
    setCloseGuard(isDirty ? () => window.confirm('Discard unsaved Brand changes?') : null);
    return () => setCloseGuard(null);
  }, [isDirty, setCloseGuard]);

  const handleUploadMedia = async (kind: AccountMediaKind, file: File) => {
    setUploadingKind(kind);
    try {
      const mediaId = await uploadMedia(kind, file);
      const url = URL.createObjectURL(file);
      if (kind === 'logo') {
        setPendingLogoMediaId(mediaId);
        setPendingLogoUrl(url);
      } else {
        setPendingFaviconMediaId(mediaId);
        setPendingFaviconUrl(url);
      }
    } finally {
      setUploadingKind(null);
    }
  };

  const handleSave = async () => {
    await saveBrand({
      name: draft.name,
      code: draft.code,
      ...(pendingLogoMediaId !== undefined ? { logoMediaId: pendingLogoMediaId } : {}),
      ...(pendingFaviconMediaId !== undefined ? { faviconMediaId: pendingFaviconMediaId } : {}),
    });
    setEditing(false);
    onSaved();
  };

  const footer = useMemo(() => {
    if (!detail || editing) {
      return null;
    }
    const isDisabledMasked = detail.previousPlatformStatus !== '';

    return (
      <EntityActionFooter
        split={{
          id: 'status',
          label: isDisabledMasked ? 'Enable' : 'Disable',
          onSelect: isDisabledMasked ? enable : disable,
          busy,
          tone: isDisabledMasked ? 'secondary' : 'danger',
          open: splitOpen,
          onToggle: () => setSplitOpen((value) => !value),
          overflow: [],
        }}
        primary={{ id: 'publish', label: 'Publish', onSelect: publish, disabled: !canPublishAccount(detail) || busy, busy }}
      />
    );
  }, [detail, editing, busy, splitOpen, disable, enable, publish]);

  useEffect(() => {
    if (!setFooter) return;
    setFooter(footer);
    return () => setFooter(null);
  }, [footer, setFooter]);

  if (loading) {
    return <p class="cz-station-empty">Loading…</p>;
  }
  if (error || !detail) {
    return <p class="cz-station-empty">{error ?? 'Account is not available.'}</p>;
  }

  if (editing) {
    return (
      <InlineEditorShell
        title="Brand"
        onSave={handleSave}
        onCancel={() => setEditing(false)}
        saving={busy}
        saveErr={error}
        isDirty={isDirty}
      >
        <AccountBrandEditor
          draft={{
            name: draft.name,
            code: draft.code,
            logoUrl: pendingLogoUrl ?? detail.brand.logoUrl,
            faviconUrl: pendingFaviconUrl ?? detail.brand.faviconUrl,
          }}
          onChange={(patch) => setDraft((current) => ({ ...current, ...patch }))}
          onUploadMedia={handleUploadMedia}
          uploading={uploadingKind}
        />
      </InlineEditorShell>
    );
  }

  return (
    <ReadBlock
      title="Brand"
      status={deriveAccountPillStatus(detail)}
      onEdit={startEditing}
      editDisabled={busy}
    >
      <p>Name: {detail.brand.name || '—'}</p>
      <p>Code: {detail.brand.code || '—'}</p>
      {detail.brand.logoUrl && <img src={detail.brand.logoUrl} alt="Logo" style="max-height:48px" />}
      {detail.brand.faviconUrl && <img src={detail.brand.faviconUrl} alt="Favicon" style="max-height:32px" />}
    </ReadBlock>
  );
}
