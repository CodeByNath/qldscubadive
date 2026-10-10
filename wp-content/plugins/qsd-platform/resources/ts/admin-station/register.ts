import { registerDataSources } from '@/station-manager/registry/dataSources';
import { registerDrawerTemplates } from '@/station-manager/registry/drawerTemplates';
import {
  registerSurfaceBindings,
  setDefaultHomeStation,
} from '@/station-manager/registry/surfaceBindings';
import { registerTemplateKits } from '@/station-manager/registry/templateKits';
import { CategoryGroupCardsKit } from './presentation/category-groups/CategoryGroupCardsKit';
import { ServiceCategoryCarousel } from './presentation/service-categories/ServiceCategoryCarousel';
import { CategoryDrawerHost } from './stations/serviceCategory/CategoryDrawerHost';
import { useServiceCategoryCards } from './stations/serviceCategory/useServiceCategoryCards';

export function registerAdminStation(): void {
  registerDataSources({
    'service-categories': useServiceCategoryCards,
  });

  registerTemplateKits({
    'category-group-cards': CategoryGroupCardsKit,
    'service-category-carousel': ServiceCategoryCarousel,
  });

  registerDrawerTemplates([
    {
      key: 'category',
      title: 'Category',
      supportedModes: ['view', 'edit'],
      content: CategoryDrawerHost,
    },
  ]);
}

export function registerPresentationPolicy(): void {
  registerSurfaceBindings([
    {
      stationId: 'account',
      surfaceId: 'account-profile',
      placement: 'presentation',
      order: 0,
      dataSourceKey: 'account',
      templateKitKey: 'account-card',
      drawerTemplateKey: 'account',
      actionIntents: [
        { id: 'view', target: 'drawer', mode: 'view' },
      ],
    },
    {
      stationId: 'services',
      surfaceId: 'service-lower-deck',
      placement: 'presentation',
      order: 1,
      dataSourceKey: 'service-catalogue',
      templateKitKey: 'service-lower-deck',
      conditions: { scope: 'current' },
      drawerTemplateKey: 'service',
      actionIntents: [
        { id: 'view', target: 'drawer', mode: 'view' },
        // Connections addresses a Category, not a Service, so it carries its
        // own intent and its own drawer key rather than overloading the
        // binding's default `service` target — one surface dispatching to more
        // than one registered drawer key.
        { id: 'view-category', target: 'drawer', mode: 'view', drawerTemplateKey: 'category' },
        // Settings' two launchers open the SAME mature Service/Category drawers
        // the binding's own `view`/`view-category` intents already open, at the
        // stable `'new'` recordId sentinel each host resolves into a pending
        // record — no separate creation drawer or registration. `mode: 'view'`
        // because a drawer never opens pre-entered into an editor, including a
        // brand-new record — it opens readable, with the empty Overview module
        // carrying its own Pending pill and Edit action.
        { id: 'create-service', target: 'drawer', mode: 'view', drawerTemplateKey: 'service' },
        { id: 'create-category', target: 'drawer', mode: 'view', drawerTemplateKey: 'category' },
      ],
    },
  ]);

  setDefaultHomeStation('services');
}
