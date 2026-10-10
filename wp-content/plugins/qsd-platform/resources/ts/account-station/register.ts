import { UserIcon } from '@/admin-station/shell/icons';
import { registerDataSources } from '@/station-manager/registry/dataSources';
import { registerDestinations } from '@/station-manager/registry/destinations';
import { registerDrawerTemplates } from '@/station-manager/registry/drawerTemplates';
import { registerNavItems } from '@/station-manager/registry/navigation';
import { registerTemplateKits } from '@/station-manager/registry/templateKits';
import { AccountCard } from './presentation/AccountCard';
import { AccountDrawerHost } from './surface/AccountDrawerHost';
import { useAccountProfileCard } from './surface/useAccountCard';

export function registerAccountStation(): void {
  registerNavItems([
    {
      id: 'account',
      label: 'Account',
      icon: UserIcon,
      activationKey: 'account',
      showInHeader: true,
      showInMenu: true,
      order: 5,
    },
  ]);

  // placement/mode are descriptive only (see destinations.ts) — the Home
  // body always resolves this destination's stationId to whatever Admin has
  // bound for it in admin-station/register.ts's registerPresentationPolicy().
  registerDestinations([
    {
      id: 'account',
      stationId: 'account',
      surfaceId: 'profile',
      placement: 'body',
      mode: 'card',
    },
  ]);

  registerDataSources({
    account: useAccountProfileCard,
  });

  registerTemplateKits({
    'account-card': AccountCard,
  });

  registerDrawerTemplates([
    {
      key: 'account',
      title: 'Account',
      supportedModes: ['view', 'edit'],
      content: AccountDrawerHost,
    },
  ]);
}
