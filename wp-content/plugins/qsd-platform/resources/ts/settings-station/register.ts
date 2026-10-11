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
// Account Phase D correction: global Connections (Tools) and Security are now
// reachable from Account ONLY — the actual relocation the batch spec asked
// for, not an additive second path. Services' own `Settings → Tools |
// Security` tabs are gone (General, carrying Service fields, is all that
// remains there); the panels, their data, their endpoints, and the
// credential-broker backend are completely unchanged — only which Station's
// `stationIds` array can reach them moved. Service fields stays Services-only
// by design: the batch spec excludes Service Meta from the global Account
// view (an Owner-excluded metafield).
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
      stationIds: ['account'],
      label: 'Rezdy importer',
      order: 10,
      panel: RezdyImporterTool,
    },
    {
      id: 'settings.api-keys',
      section: 'security',
      stationIds: ['account'],
      label: 'API Keys',
      order: 10,
      panel: SecurityApiKeysPanel,
    },
  ]);
}
