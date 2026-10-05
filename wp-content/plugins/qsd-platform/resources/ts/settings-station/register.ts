import { SettingsIcon } from '@/admin-station/shell/icons';
import { registerDataSources } from '@/station-manager/registry/dataSources';
import { registerDestinations } from '@/station-manager/registry/destinations';
import { registerNavItems } from '@/station-manager/registry/navigation';
import { registerTemplateKits } from '@/station-manager/registry/templateKits';
import { SettingsDeck } from './presentation/SettingsDeck';
import { useSettingsHome } from './useSettingsHome';

// Settings Station registration — navigation, destination, its one data
// source, and its deck kit. No drawer template: Settings is configuration and
// has no lifecycle-managed record. Imported only by the Admin Station entry.
export function registerSettingsStation(): void {
  registerNavItems([
    {
      id: 'settings',
      label: 'Settings',
      icon: SettingsIcon,
      activationKey: 'settings',
      showInHeader: true,
      showInMenu: true,
      order: 90,
    },
  ]);

  registerDestinations([
    {
      id: 'settings',
      stationId: 'settings',
      surfaceId: 'settings-home',
      placement: 'body',
      mode: 'summary',
      conditions: { scope: 'current' },
    },
  ]);

  registerDataSources({
    'settings-home': useSettingsHome,
  });

  registerTemplateKits({
    'settings-deck': SettingsDeck,
  });
}
