import { registerStationSettings } from '@/station-manager/registry/stationSettings';
import { RezdyImporterTool } from './presentation/RezdyImporterTool';
import { SecurityApiKeysPanel } from './presentation/SecurityApiKeysPanel';
import { ServiceMetaSchemaLane } from './presentation/ServiceMetaSchemaLane';

// Settings registration — Settings is a tab pattern inside a Station, not a
// Station, so it registers no navigation, destination or deck. It contributes
// its configuration panels to the Stations that present them: today the
// Services Station's `Settings → General | Tools | Security`. The data behind
// each panel stays with the Settings backend; presenting a panel in another
// Station later reuses the same panel and the same data. Imported only by the
// Admin Station entry.
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
      stationIds: ['services'],
      label: 'Rezdy importer',
      order: 10,
      panel: RezdyImporterTool,
    },
    {
      id: 'settings.api-keys',
      section: 'security',
      stationIds: ['services'],
      label: 'API Keys',
      order: 10,
      panel: SecurityApiKeysPanel,
    },
  ]);
}
