// Settings Home deck — the Settings Station's one presentation kit.
//
// Composition only: a context bar and the shared station tab set holding the
// Settings-owned Tools. Settings is configuration, not a domain record, so it
// renders no catalogue, drawer, or lifecycle footer. It is unrelated to Service
// Home's `Settings` lane (Create Service / Create Category launchers).
//
// The template-kit props are unused: Settings Tools read their own Settings
// endpoints through their hooks, and dispatch no drawer intent.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import type { TemplateKitProps } from '@/station-manager/registry/templateKits';
import { StationTabSet, type StationTabSetClasses } from '@/admin-station/presentation/StationTabSet';
import { SettingsIcon } from '@/admin-station/shell/icons';
import { SettingsConnectionsLane } from './SettingsConnectionsLane';
import { ServiceMetaSchemaLane } from './ServiceMetaSchemaLane';

export type SettingsDeckTab = 'connections' | 'service-meta';

const DECK_CLASSES: StationTabSetClasses = {
  list:  'cz-station-tabset__list cz-settings-deck__tabs',
  tab:   'cz-station-tabset__tab',
  panel: 'cz-station-tabset__panel cz-settings-deck__panel',
};

const TABS: { id: SettingsDeckTab; label: string }[] = [
  { id: 'connections',  label: 'Connections & Security' },
  { id: 'service-meta', label: 'Service Meta' },
];

export function SettingsDeck(_props: TemplateKitProps): VNode {
  const [activeTab, setActiveTab] = useState<SettingsDeckTab>('connections');

  return (
    <section class="cz-settings-deck" aria-label="Platform Settings">
      <div class="cz-settings-deck__bar">
        <span class="cz-settings-deck__context-icon" aria-hidden="true"><SettingsIcon /></span>
        <h3 class="cz-settings-deck__context-name">Platform Settings</h3>
      </div>

      <StationTabSet
        label="Settings tools"
        items={TABS}
        selectedId={activeTab}
        onSelect={setActiveTab}
        classes={DECK_CLASSES}
        renderPanel={(tab) => (tab === 'connections' ? <SettingsConnectionsLane /> : <ServiceMetaSchemaLane />)}
      />
    </section>
  );
}
