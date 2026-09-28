// Service-domain wire/application types shared by the Service Station, the
// Category Station, and the drawer-kit resolvers.
//
// These are the Service record as the Admin Station holds it. The Service
// entity carries no price, tier, or availability; those arrive later as their
// own Service modules, never as Overview fields.

export interface Category {
  id:           number | null;
  platformId?:  string;
  name:         string;
  slug:         string;
  description?: string;
}

export interface ServiceInclusion {
  id:    string;
  label: string;
}

export interface ServiceFaq {
  id:       string;
  question: string;
  answer:   string;
}

export type PlatformStatus = 'active' | 'disabled' | 'archived' | 'trashed';
export type ModuleTransition = 'settled' | 'pending' | 'not-configured';

export interface ModuleStatus {
  overview:   ModuleTransition;
  inclusions: ModuleTransition;
  faqs:       ModuleTransition;
}

export interface ServiceMeta {
  platform_status: PlatformStatus;
  // The mask signal Disable/Enable use: non-empty while platform_status is
  // 'disabled' means an explicit Disable applied and captured what to restore;
  // empty means 'disabled' because the Service has never been published.
  previous_platform_status?: 'active' | 'disabled' | '';
  module_status: ModuleStatus;
  /** @deprecated Use platform_status instead. */
  is_active?: boolean | null;
}

export interface ServiceItem {
  id: number;
  // Present on authoritative admin Service projections.
  platformId?: string;
  title:       string;
  slug:        string;
  excerpt:     string;
  content:     string;
  categories:  Category[];
  inclusions:  ServiceInclusion[];
  faqs:        ServiceFaq[];
  meta:        ServiceMeta;
}
