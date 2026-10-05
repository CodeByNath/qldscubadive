// Neutral Service drawer composition — the mature Service drawer, owned by the
// entity and mountable under any host.
//
// It reuses the already-approved presentation unchanged: EntityDrawer assembles
// the Overview / Included Features / Common Questions modules (status pills,
// notification panels, module ActionFooters) from the Service manifest; the
// Connections tab carries the Pricing Summary; each module edits in place through
// OverviewShell / ChildShell + InlineEditorShell while the others stay readable.
//
// It imports neither host — not the old Command Centre shell, not the Admin
// Station shell. All coordination goes through useServiceDrawerController; all
// host concerns (record footer, close-guard, close, refresh) go through the
// EntityDrawerHostBridge. That is what lets ServiceViewStep and the Admin Station
// host adapter render exactly this composition.

import { useEffect } from 'preact/hooks';
import { EntityDrawer } from '@/drawer-kit/EntityDrawer';
import type { EntityDrawerEditingModule } from '@/drawer-kit/EntityDrawer';
import { SERVICE_ENTITY } from './schema/entities/service';
import type { OverviewDraft, InclusionsDraft, FaqsDraft } from '@/service-station';
import { useServiceDrawerController } from './useServiceDrawerController';
import { ServiceDrawerFooter } from './ServiceDrawerFooter';
import { ServiceDrawerDialogs } from './ServiceDrawerDialogs';
import type { ServiceDrawerContentProps } from './serviceDrawerTypes';

export function ServiceDrawerContent(props: ServiceDrawerContentProps) {
  const { bridge } = props;
  const c = useServiceDrawerController(props);

  const editing: EntityDrawerEditingModule | null =
    c.editingSection === 'overview' && c.overviewDraft ? {
      module: 'overview',
      session: {
        draft: c.overviewDraft,
        patch: (patch) => c.setOverviewDraft((current) => current ? { ...current, ...(patch as Partial<OverviewDraft>) } : current),
        replace: (next) => c.setOverviewDraft(next as OverviewDraft),
        onSave: c.handleSaveOverview,
        onCancel: c.handleCancelEdit,
        saving: c.saving,
        saveErr: c.saveErr,
        isDirty: c.isEditorDirty,
        extras: {
          categories: c.localCategories,
          catDescription: c.catDesc,
          onCatDescriptionChange: c.setCatDesc,
          onCreateCategory: c.createInlineCategory,
        },
      },
    } : c.editingSection === 'inclusions' && c.inclusionsDraft ? {
      module: 'inclusions',
      session: {
        draft: c.inclusionsDraft,
        patch: (patch) => c.setInclusionsDraft((current) => current ? { ...current, ...(patch as Partial<InclusionsDraft>) } : current),
        replace: (next) => c.setInclusionsDraft(next as InclusionsDraft),
        onSave: c.handleSaveInclusions,
        onCancel: c.handleCancelEdit,
        saving: c.saving,
        saveErr: c.saveErr,
        isDirty: c.isEditorDirty,
      },
    } : c.editingSection === 'faqs' && c.faqsDraft ? {
      module: 'faqs',
      session: {
        draft: c.faqsDraft,
        patch: (patch) => c.setFaqsDraft((current) => current ? { ...current, ...(patch as Partial<FaqsDraft>) } : current),
        replace: (next) => c.setFaqsDraft(next as FaqsDraft),
        onSave: c.handleSaveFaqs,
        onCancel: c.handleCancelEdit,
        saving: c.saving,
        saveErr: c.saveErr,
        isDirty: c.isEditorDirty,
      },
    } : null;

  // Publish the record-level footer into the host's footer region. Re-runs on
  // the same gating inputs the old host's footer effect used; edit mode leaves
  // the slot to InlineEditorShell's own Save/Cancel footer (handled below).
  useEffect(() => {
    bridge.setFooter(c.editingSection ? null :
      <ServiceDrawerFooter
        tab={c.tab}
        platformStatus={c.platformStatus}
        isDisabledMasked={c.isDisabledMasked}
        isNewNeverPublished={c.isNewNeverPublished}
        hasBeenPublished={c.hasBeenPublished}
        canPublish={c.canPublish}
        loadingStatus={c.station.loading.status}
        splitOpen={c.splitOpen}
        setSplitOpen={c.setSplitOpen}
        onToggleActive={c.handleToggleActive}
        onArchive={c.handleArchive}
        onTrash={c.handleTrash}
        onPublish={c.openPublishModal}
        onClose={c.requestClose}
      />,
    );
    return () => bridge.setFooter(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [c.tab, c.platformStatus, c.isDisabledMasked, c.splitOpen, c.station.loading.status, c.canPublish, c.hasBeenPublished, c.isNewNeverPublished, c.editingSection, bridge]);

  return (
    <>
      <EntityDrawer
        entity={SERVICE_ENTITY}
        tab={c.tab}
        onSelectTab={c.selectServiceTab}
        bindings={{
          overview:   c.overviewShellBinding,
          inclusions: c.inclusionsShellBinding,
          faqs:       c.faqsShellBinding,
        }}
        openPanel={c.openPanel}
        onTogglePanel={c.togglePanel}
        editing={editing}
      >
        {c.saveOk && <div class="cz-admin-ok-msg">Changes saved.</div>}
      </EntityDrawer>

      <ServiceDrawerDialogs c={c} />
    </>
  );
}
