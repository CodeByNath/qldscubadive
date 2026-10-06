// Service Home Settings lane — the Services Station's Settings tab.
//
// It presents the shared `General | Tools | Security` pattern for the
// `services` Station. What each section holds is registered by its owner with
// Station Manager: Service registers its own creation launchers under General
// (ServiceCreateLaunchers); Settings registers its configuration tools and the
// Security API Keys panel. This lane imports neither the Settings peer nor any
// of its panels, and owns none of their data.

import type { VNode } from 'preact';
import type { StationIntentDispatch } from '@/station-manager/registry/templateKits';
import { StationSettings } from '@/admin-station/presentation/StationSettings';

export function ServiceSettingsLane({ onIntent }: { onIntent: StationIntentDispatch }): VNode {
  return <StationSettings stationId="services" onIntent={onIntent} />;
}
