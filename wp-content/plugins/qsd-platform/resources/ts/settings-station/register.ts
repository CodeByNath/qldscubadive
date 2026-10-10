import { registerStationSettings } from '@/station-manager/registry/stationSettings';
import { RezdyImporterTool } from './presentation/RezdyImporterTool';
import { SecurityApiKeysPanel } from './presentation/SecurityApiKeysPanel';
import { ServiceMetaSchemaLane } from './presentation/ServiceMetaSchemaLane';

// Settings registration — Settings is a tab pattern inside a Station, not a
// Station, so it registers no navigation, destination or deck. It contributes
// its configuration panels to the Stations that present them. The data behind
// each panel stays with the Settings backend; presenting a panel in another
// Station reuses the same panel and the same data, never a second instance.
// Imported only by the Admin Station entry.
//
// Account Phase D: Tools and Security are also reachable from Account's own
// Settings section now (global Connections/Security relocation) — additive,
// not a move; Services keeps its own `Settings → Tools | Security` until a
// separate, explicit retirement step removes it there, per the batch's
// "retire standalone Settings navigation only after regression proof."
// Service fields stays Services-only by design: the batch spec excludes
// Service Meta from the global Account view (an Owner-excluded metafield),
// and its routes/data/access are unchanged either way.
export function registerSettingsStation(): void {
  registerStationSettings([
    {
      id: 'settings.service-meta',
      section: 'general',
      stationIds: ['services'],
      label: 'Service fields',
      order: 20,
      panel: ServiceMetaSchemaLane,
    },
    {
      id: 'settings.rezdy-importer',
      section: 'tools',
      stationIds: ['services', 'account'],
      label: 'Rezdy importer',
      order: 10,
      panel: RezdyImporterTool,
    },
    {
      id: 'settings.api-keys',
      section: 'security',
      stationIds: ['services', 'account'],
      label: 'API Keys',
      order: 10,
      panel: SecurityApiKeysPanel,
    },
  ]);
}
