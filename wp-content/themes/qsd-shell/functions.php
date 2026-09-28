<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
 * QSD Shell — the runtime document shell.
 *
 * WordPress here is a runtime and storage host: the QSD Platform plugin serves
 * the Admin Station at /station/ with its own document, and the public website
 * is a separate front end that reads the qsd/v1 API. This theme therefore owns
 * no layout, assets, or content rendering.
 */
add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('html5', ['style', 'script']);
});

// Nothing WordPress renders on its own is meant to be indexed.
add_filter('wp_robots', 'wp_robots_no_robots');
