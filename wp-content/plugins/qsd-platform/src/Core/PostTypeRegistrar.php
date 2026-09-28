<?php

namespace QSD\Platform\Core;

/**
 * Declares the platform's WordPress post types. Declaration only — each owning
 * Station holds the behaviour.
 *
 * Every platform entity is private to WordPress: no public URL, archive,
 * query var, search result, sitemap entry, or core /wp/v2 REST route. The
 * platform lifecycle (platform_status, drafts, bin) never writes post_status,
 * so WordPress's own public output could not honour it. All reads go through
 * the qsd/v1 API, where each Station applies its lifecycle.
 */
class PostTypeRegistrar
{
    public const SERVICE = 'qsd_service';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
    }

    public function registerPostTypes(): void
    {
        register_post_type(self::SERVICE, [
            'labels'              => [
                'name'          => 'Services',
                'singular_name' => 'Service',
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_nav_menus'   => false,
            'show_in_rest'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => ['title', 'editor', 'excerpt'],
        ]);
    }
}
