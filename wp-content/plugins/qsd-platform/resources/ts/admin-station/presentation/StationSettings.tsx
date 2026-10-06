// Station Settings — the shared `General | Tools | Security` tab pattern a
// Station shows inside its own Settings lane.
//
// Presentation only. The sections and panels come from the Station Manager
// station-settings registry, where each owner registered its own
// contributions; this file renders whichever sections the Station has, in a
// fixed order, on the shared tab set. It names no Station, panel, endpoint or
// permission, and owns none of the data a panel shows.

import { useState } from 'preact/hooks';
import type { VNode } from 'preact';
import { resolveStationSettings, type StationSettingsSection } from '@/station-manager/registry/stationSettings';
import type { StationIntentDispatch } from '@/station-manager/registry/templateKits';
import { StationTabSet, type StationTabSetClasses } from './StationTabSet';

const SECTION_LABEL: Record<StationSettingsSection, string> = {
  general:  'General',
  tools:    'Tools',
  security: 'Security',
};

const CLASSES: StationTabSetClasses = {
  list:  'cz-station-tabset__list cz-station-settings__tabs',
  tab:   'cz-station-tabset__tab',
  panel: 'cz-station-tabset__panel cz-station-settings__panel',
};

export function StationSettings({ stationId, onIntent }: { stationId: string; onIntent: StationIntentDispatch }): VNode {
  const sections = resolveStationSettings(stationId);
  const [selected, setSelected] = useState<StationSettingsSection>(sections[0]?.section ?? 'general');

  if (sections.length === 0) {
    return <p class="cz-station-empty">This Station has no settings yet.</p>;
  }

  return (
    <div class="cz-station-settings" data-station-settings={stationId}>
      <StationTabSet
        label="Settings sections"
        items={sections.map(({ section }) => ({ id: section, label: SECTION_LABEL[section] }))}
        selectedId={selected}
        onSelect={setSelected}
        classes={CLASSES}
        renderPanel={(id) => (
          <div class="cz-station-settings__section" data-settings-section={id}>
            {sections.find((s) => s.section === id)?.contributions.map(({ id: key, label, panel: Panel }) => (
              <section key={key} class="cz-station-settings__block" data-settings-contribution={key} aria-label={label}>
                <h4 class="cz-station-settings__heading">{label}</h4>
                <Panel onIntent={onIntent} />
              </section>
            ))}
          </div>
        )}
      />
    </div>
  );
}
