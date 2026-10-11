// Service Home Settings lane — the Services Station's Settings tab.
//
// It presents the shared `General | Tools | Security` pattern for the
// `services` Station, resolved purely from what is registered — currently
// General only (Service's own creation launchers, and Settings' Service
// fields panel). Tools/Security moved to Account's own Settings section; this
// lane would show them again the moment a Services contribution ever
// registers for those sections. This lane imports neither the Settings peer
// nor any of its panels, and owns none of their data.

import type { VNode } from 'preact';
import type { StationIntentDispatch } from '@/station-manager/registry/templateKits';
import { StationSettings } from '@/admin-station/presentation/StationSettings';

export function ServiceSettingsLane({ onIntent }: { onIntent: StationIntentDispatch }): VNode {
  return <StationSettings stationId="services" onIntent={onIntent} />;
}
