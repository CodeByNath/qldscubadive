import { ServicesIcon } from '@/admin-station/shell/icons';
import { registerDataSources } from '@/station-manager/registry/dataSources';
import { registerDestinations } from '@/station-manager/registry/destinations';
import { registerDrawerTemplates } from '@/station-manager/registry/drawerTemplates';
import { registerNavItems } from '@/station-manager/registry/navigation';
import { registerStationSettings } from '@/station-manager/registry/stationSettings';
import { registerTemplateKits } from '@/station-manager/registry/templateKits';
import { ServiceCreateLaunchers } from './presentation/ServiceCreateLaunchers';
import { ServiceLowerDeck } from './presentation/ServiceLowerDeck';
import { ServiceDrawerHost } from './surface/ServiceDrawerHost';
import { useServiceCards } from './surface/useServiceCards';
import { useServiceCatalogue } from './surface/useServiceCatalogue';

export function registerServiceStation(): void {
  registerNavItems([
    {
      id: 'services',
      label: 'Services',
      icon: ServicesIcon,
      activationKey: 'services',
      showInHeader: true,
      showInMenu: true,
      order: 10,
    },
  ]);

  registerDestinations([
    {
      id: 'services',
      stationId: 'services',
      surfaceId: 'catalog',
      placement: 'body',
      mode: 'table',
      conditions: { scope: 'current' },
    },
  ]);

  registerDataSources({
    services: useServiceCards,
    'service-catalogue': useServiceCatalogue,
  });

  // Service Home's presentation placement is the lower deck; the catalogue is
  // one lane inside it rather than a surface of its own.
  registerTemplateKits({
    'service-lower-deck': ServiceLowerDeck,
  });

  // Services → Settings → General: Service's own creation launchers.
  registerStationSettings([
    {
      id: 'service.create',
      section: 'general',
      stationIds: ['services'],
      label: 'Create',
      order: 10,
      panel: ServiceCreateLaunchers,
    },
  ]);

  registerDrawerTemplates([
    {
      key: 'service',
      title: 'Service',
      supportedModes: ['view', 'edit'],
      content: ServiceDrawerHost,
    },
  ]);
}
