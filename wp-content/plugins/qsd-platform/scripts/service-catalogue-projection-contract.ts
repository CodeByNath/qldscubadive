import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { serviceMatchesCategory } from '../resources/ts/service-station/presentation/model';
import type { ServiceCatalogueItem } from '../resources/ts/service-station/presentation/types';

function check(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(`Service catalogue projection contract: ${message}`);
}

const service: ServiceCatalogueItem = {
  id: 42,
  platformId: 'QSDS2A7KZ',
  name: 'Open Water Diver',
  slug: 'open-water-diver',
  description: 'First scuba certification.',
  createdAt: null,
  categories: [{ id: 5, name: 'Scuba Courses', slug: 'scuba-courses' }],
  inclusionCount: 2,
  faqCount: 1,
  platformStatus: 'active',
  presentationStatus: 'active',
  scope: 'current',
};

check(serviceMatchesCategory(service, 'scuba-courses'), 'Category filtering uses the direct Service Category');
check(!serviceMatchesCategory(service, 'infrastructure-group'), 'an unrelated grouping label does not match Category');

const source = (path: string) => readFileSync(resolve(process.cwd(), path), 'utf8');
const catalogue = source('resources/ts/service-station/presentation/ServiceCatalogue.tsx');
const model = source('resources/ts/service-station/presentation/model.ts');
const adapter = source('resources/ts/service-station/surface/serviceCatalogueAdapter.ts');
const serviceApi = source('resources/ts/service-station/api.ts');
const serviceTypes = source('resources/ts/service-station/types.ts').split('// ── DETAIL')[0];
const serviceController = source('src/Modules/Service/Http/ServiceController.php')
  .split('public function listServices')[1]
  .split('public function createService')[0];
check(!/apiClient|fetch[A-Z]|api\/endpoints/.test(catalogue), 'presentation contains no endpoint call');
check(!/familyGroups|group_name|packageFamil/.test(`${catalogue}\n${model}\n${adapter}`), 'the catalogue carries no Package Family or Category Group projection');
check(!/group_id|group_name/.test(serviceTypes), 'Service catalogue summary exposes no taxonomy-parent fields');
check(!/group_id|group_name/.test(serviceController), 'Service catalogue response exposes no taxonomy-parent fields');
check(serviceController.includes('CategoryMeta::STATION_ROLE_CATEGORY'), 'Service catalogue response retains direct Category-role terms');
// A post id is a small integer; a parsed date is epoch milliseconds. Comparing
// one against the other pinned every dateless Service to one end of the list.
check(
  !/Number\.isFinite\(\w+\)\s*\?\s*\w+\s*:\s*\w+\.id/.test(catalogue),
  'sorting never substitutes a native id for a missing timestamp',
);
// Reset and its disabled state must cover every toolbar control, sort included.
check(
  /const resetFilters[\s\S]*?setSort\('newest'\)/.test(catalogue)
    && /hasFilters = Boolean\([\s\S]*?sort !== 'newest'/.test(catalogue),
  'Reset clears every toolbar control and reports itself accurately',
);
check(catalogue.includes("onIntent(service.id, 'view')"), 'mature Service drawer intent keeps the native Service ID');
check(adapter.includes('platformId:         summary.platformId'), 'catalogue adapter preserves immutable platform identity');
check(
  serviceApi.includes('const { platform_id, ...rest } = value')
    && serviceApi.includes('platformId: platform_id')
    && serviceApi.includes('response.stations.map(mapPlatformId)'),
  'endpoint adapters map backend platform identity',
);
check(!/Number\(.*service.*id|String\(.*service.*id/i.test(`${catalogue}\n${model}\n${adapter}`), 'catalogue introduces no Service ID coercion');

console.log('Service catalogue projection contract checks passed.');
