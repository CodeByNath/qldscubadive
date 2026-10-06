// Station Settings registry — the reusable `Settings → General | Tools |
// Security` tab pattern a Station presents.
//
// Settings is a tab inside a Station, not a Station. Each owner registers its
// own panels as contributions to one of the three sections, for the Stations
// that should present them: Service registers its creation launchers, Settings
// registers its configuration tools and the Security API Keys panel. Data
// stays with whoever registered the panel: presenting the same contribution in
// two Stations shows one owner's state twice, never a copy.
//
// The coordinator only orders and resolves; it names no Station, section
// content, endpoint or permission.

import type { VNode } from 'preact';
import type { StationIntentDispatch } from './templateKits';

export type StationSettingsSection = 'general' | 'tools' | 'security';

export const STATION_SETTINGS_SECTIONS: readonly StationSettingsSection[] = ['general', 'tools', 'security'];

export interface StationSettingsPanelProps {
  // The hosting Station's own intent dispatcher, for a panel that opens one of
  // that Station's drawers (e.g. a creation launcher).
  onIntent: StationIntentDispatch;
}

export interface StationSettingsContribution {
  id: string;
  section: StationSettingsSection;
  // The Stations that present this contribution in their Settings tab.
  stationIds: string[];
  label: string;
  order: number;
  panel: (props: StationSettingsPanelProps) => VNode;
}

export interface ResolvedStationSettingsSection {
  section: StationSettingsSection;
  contributions: StationSettingsContribution[];
}

const registered: StationSettingsContribution[] = [];

let locked = false;
let resolversReady = false;
let index: StationSettingsContribution[] = [];

function assertRegistrationOpen(): void {
  if (locked) {
    throw new Error('[StationManager] station-settings registry is finalized.');
  }
}

function assertResolversReady(): void {
  if (!resolversReady) {
    throw new Error('[StationManager] station registry has not been finalized.');
  }
}

export function registerStationSettings(list: StationSettingsContribution[]): void {
  assertRegistrationOpen();

  const pending = new Set<string>();
  for (const contribution of list) {
    if (registered.some((c) => c.id === contribution.id) || pending.has(contribution.id)) {
      throw new Error(`[StationManager] duplicate station-settings contribution '${contribution.id}'.`);
    }
    if (!STATION_SETTINGS_SECTIONS.includes(contribution.section)) {
      throw new Error(`[StationManager] station-settings contribution '${contribution.id}' names unknown section '${contribution.section}'.`);
    }
    if (contribution.stationIds.length === 0) {
      throw new Error(`[StationManager] station-settings contribution '${contribution.id}' is presented by no Station.`);
    }
    pending.add(contribution.id);
  }

  registered.push(...list);
}

/**
 * The sections a Station presents, in General → Tools → Security order, each
 * with its contributions sorted by `order` (registration order breaks ties).
 * A section with no contribution for this Station is omitted.
 */
export function resolveStationSettings(stationId: string): ResolvedStationSettingsSection[] {
  assertResolversReady();
  const mine = index.filter((c) => c.stationIds.includes(stationId));
  return STATION_SETTINGS_SECTIONS
    .map((section) => ({
      section,
      contributions: mine
        .map((c, position) => ({ c, position }))
        .filter(({ c }) => c.section === section)
        .sort((a, b) => a.c.order - b.c.order || a.position - b.position)
        .map(({ c }) => c),
    }))
    .filter((resolved) => resolved.contributions.length > 0);
}

/** @internal Finalization is coordinated exclusively by registry/boot.ts. */
export function _finalizeStationSettingsRegistry(): void {
  locked = true;
  index = [...registered];
}

/** @internal Public resolvers open only after every finalize assertion passes. */
export function _enableStationSettingsResolvers(): void {
  resolversReady = true;
}
