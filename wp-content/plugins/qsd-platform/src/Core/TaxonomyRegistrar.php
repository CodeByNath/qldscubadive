<?php

namespace QSD\Platform\Core;

/**
 * Declares the platform's WordPress taxonomies. Declaration only — the
 * Category Station (Admin\Http\AdminCategoriesController + CategoryMeta) owns
 * behaviour and lifecycle.
 *
 * Private for the same reason as PostTypeRegistrar: no term archives, query
 * vars, or core REST routes; reads go through the qsd/v1 API.
 */
class TaxonomyRegistrar
{
    public const SERVICE_CATEGORY = 'qsd_service_category';

    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomies']);
    }

    public function registerTaxonomies(): void
    {
        register_taxonomy(self::SERVICE_CATEGORY, [PostTypeRegistrar::SERVICE], [
            'labels'             => [
                'name'          => 'Service Categories',
                'singular_name' => 'Service Category',
            ],
            'hierarchical'       => true,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_nav_menus'  => false,
            'show_tagcloud'      => false,
            'show_in_rest'       => false,
            'rewrite'            => false,
            'query_var'          => false,
        ]);
    }
}
