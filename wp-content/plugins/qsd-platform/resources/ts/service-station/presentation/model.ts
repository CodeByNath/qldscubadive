import type { ServiceCatalogueItem } from './types';

export interface ServiceCatalogueFilterOption {
  value: string;
  label: string;
}

export function serviceMatchesCategory(
  item: ServiceCatalogueItem,
  selectedCategorySlug: string,
): boolean {
  return selectedCategorySlug === 'all'
    || item.categories.some((category) => category.slug === selectedCategorySlug);
}
