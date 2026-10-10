// Account Home Settings section — the Account Station's Settings tab.
//
// It presents the shared `General | Tools | Security` pattern for the
// `account` Station, exactly as ServiceSettingsLane does for `services`. What
// each section holds is registered by its owner with Station Manager; this
// file imports neither the Settings peer nor any of its panels, and owns none
// of their data. A second, additive placement — Phase D relocates reachability
// of the existing Tools/Security panels here, never their backend.

import type { VNode } from 'preact';
import type { TemplateKitProps } from '@/station-manager/registry/templateKits';
import { StationSettings } from '@/admin-station/presentation/StationSettings';

export function AccountSettingsSection({ onIntent }: TemplateKitProps): VNode {
  return <StationSettings stationId="account" onIntent={onIntent} />;
}
