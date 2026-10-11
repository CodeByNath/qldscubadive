// Account drawer controller — coordination layer, mirroring
// useCategoryDrawerController at Account's much smaller scale: one module
// (Brand), no travel actions, no numeric/string record id.

import { useCallback, useEffect, useRef, useState } from 'preact/hooks';
import { useAccountStation } from '@/account-station/useAccountStation';
import { accountBrandModule, evaluateModule } from '@/drawer-kit/utils/moduleNotifications';
import type { ShellBinding } from '@/drawer-kit/schema/types';
import type { DrawerTabId } from '@/drawer-kit/DrawerTabs';
import type { AccountMediaKind } from '@/account-station/types';
import {
  useAutoDismiss,
  useGuardedClose,
  useLifecycleRunner,
  useOutsideClickDismiss,
} from '../shared/drawerChrome';
import type { AccountBrandShellData } from '../schema/bindings/account';
import type { AccountDrawerContentProps, AccountExitDialog } from './accountDrawerTypes';
import type { AccountBrandDraft } from '../editors/AccountBrandEditor';

export function useAccountDrawerController({ initialTab, initialEdit, bridge }: AccountDrawerContentProps) {
  const station = useAccountStation(bridge.onMutationComplete);
  const [tab, setTab] = useState<DrawerTabId>(initialTab ?? 'details');
  const [openPanel, setOpenPanel] = useState<string | null>(null);
  const [splitOpen, setSplitOpen] = useState(false);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<AccountBrandDraft | null>(null);
  const [original, setOriginal] = useState<AccountBrandDraft | null>(null);
  const [uploadingKind, setUploadingKind] = useState<AccountMediaKind | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveErr, setSaveErr] = useState<string | null>(null);
  const [saveOk, setSaveOk] = useState(false);
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [exitDialog, setExitDialog] = useState<AccountExitDialog>(null);

  useAutoDismiss(saveOk, () => setSaveOk(false), 3000);
  useOutsideClickDismiss(splitOpen, () => setSplitOpen(false));

  const isDirty = editing && draft !== null && original !== null && (
    draft.name !== original.name
    || draft.code !== original.code
    || draft.logoMediaId !== original.logoMediaId
    || draft.faviconMediaId !== original.faviconMediaId
  );

  const openBrandEditor = useCallback(() => {
    if (!station.detail) return;
    const seed: AccountBrandDraft = {
      name: station.detail.brand.name,
      code: station.detail.brand.code,
      logoUrl: station.detail.brand.logoUrl,
      faviconUrl: station.detail.brand.faviconUrl,
    };
    setDraft(seed);
    setOriginal(seed);
    setEditing(true);
    setOpenPanel(null);
  }, [station.detail]);

  const initialEditOpened = useRef(false);
  useEffect(() => {
    if (!initialEdit || initialEditOpened.current || !station.detail) return;
    initialEditOpened.current = true;
    openBrandEditor();
  }, [initialEdit, openBrandEditor, station.detail]);

  const cancelEdit = useCallback(() => {
    setEditing(false);
    setDraft(null);
    setOriginal(null);
  }, []);

  const handleUploadMedia = useCallback(async (kind: AccountMediaKind, file: File) => {
    setUploadingKind(kind);
    try {
      const mediaId = await station.uploadMedia(kind, file);
      const url = URL.createObjectURL(file);
      setDraft((current) => current && (
        kind === 'logo'
          ? { ...current, logoMediaId: mediaId, logoUrl: url }
          : { ...current, faviconMediaId: mediaId, faviconUrl: url }
      ));
    } finally {
      setUploadingKind(null);
    }
  }, [station]);

  const saveBrand = useCallback(async () => {
    if (!draft) return;
    setSaving(true);
    setSaveErr(null);
    try {
      await station.saveBrand({
        name: draft.name,
        code: draft.code,
        ...(draft.logoMediaId !== undefined ? { logoMediaId: draft.logoMediaId } : {}),
        ...(draft.faviconMediaId !== undefined ? { faviconMediaId: draft.faviconMediaId } : {}),
      });
      setEditing(false);
      setDraft(null);
      setOriginal(null);
      setSaveOk(true);
    } catch (error) {
      setSaveErr(error instanceof Error ? error.message : 'Could not save the Account Brand.');
    } finally {
      setSaving(false);
    }
  }, [draft, station]);

  const { guard, resolveExit, closeBypassingGuard } = useGuardedClose(bridge, () => {
    if (!isDirty) return true;
    setExitDialog('unsaved');
    return false;
  });

  const selectTab = useCallback((next: DrawerTabId) => {
    guard(() => setTab(next));
  }, [guard]);

  const handleExitDiscard = useCallback(() => {
    cancelEdit();
    setExitDialog(null);
    resolveExit();
  }, [cancelEdit, resolveExit]);

  const { actionError, setActionError, run: runLifecycle } = useLifecycleRunner(
    closeBypassingGuard,
    'The Account action failed.',
    () => setSplitOpen(false),
  );

  const isActive = station.detail?.platformStatus === 'active';
  const isDisabledMasked = (station.detail?.previousPlatformStatus ?? '') !== '';

  // Account's backend has no separate Settle route (AccountBrand::publish
  // settles-then-activates in one call) — Publish is always the right
  // operation, active or not, unlike Category's settle/publish split.
  const handleConfirmPublish = useCallback(async () => {
    setShowPublishModal(false);
    await runLifecycle(station.publish);
  }, [runLifecycle, station]);

  const canPublish = !!station.detail?.bootstrapped;

  const brandBinding: ShellBinding<AccountBrandShellData> = {
    data: {
      platformId: station.detail?.nodes.profile.platformId ?? '',
      bootstrapped: station.detail?.bootstrapped ?? false,
      name: station.detail?.brand.name ?? '',
      code: station.detail?.brand.code ?? '',
      logoUrl: station.detail?.brand.logoUrl ?? null,
      faviconUrl: station.detail?.brand.faviconUrl ?? null,
    },
    state: evaluateModule(accountBrandModule, {
      bootstrapped: station.detail?.bootstrapped ?? false,
      hasDraft: station.detail?.hasDraft ?? false,
      name: station.detail?.brand.name ?? '',
      code: station.detail?.brand.code ?? '',
    }, {
      platformStatus: station.detail?.platformStatus ?? 'disabled',
      moduleTransition: station.detail?.moduleStatus.brand,
      hasDraft: station.detail?.hasDraft ?? false,
      platformLabel: 'Account',
      disabled: isDisabledMasked,
    }),
    hasDraft: station.detail?.hasDraft ?? false,
    handlers: { edit: openBrandEditor },
  };

  return {
    station,
    tab,
    selectTab,
    openPanel,
    togglePanel: (m: string) => setOpenPanel((p) => (p === m ? null : m)),
    brandBinding,
    editing,
    draft,
    setDraft,
    isDirty,
    saving,
    saveErr,
    saveOk,
    openBrandEditor,
    saveBrand,
    cancelEdit,
    handleUploadMedia,
    uploadingKind,
    splitOpen,
    setSplitOpen,
    actionError,
    isActive,
    isDisabledMasked,
    canPublish,
    showPublishModal,
    setShowPublishModal,
    openPublishModal: () => setShowPublishModal(true),
    handleConfirmPublish,
    exitDialog,
    setExitDialog,
    handleExitDiscard,
    requestClose: bridge.close,
    handleToggleActive: () => { setActionError(null); void runLifecycle(isDisabledMasked ? station.enable : station.disable); },
  };
}

export type AccountDrawerController = ReturnType<typeof useAccountDrawerController>;
