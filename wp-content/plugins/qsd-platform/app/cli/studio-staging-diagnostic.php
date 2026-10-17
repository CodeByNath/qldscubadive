<?php

/**
 * CLI-only entry point for `StudioStagingDiagnostic::report()` — printed as
 * JSON to stdout, invoked over SSH by the `deploy-staging` CI job
 * immediately after a staging deploy so Reviewer/Owner get real-server
 * activation evidence without a browser session or wp-admin access.
 *
 * Refuses any non-CLI SAPI immediately: this file sits inside the plugin's
 * web-servable directory tree (so the existing deploy rsync ships it, no
 * workflow change needed to deliver it), so it must never execute anything
 * if a web server ever routes a direct request to it.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = __DIR__;
while (!is_file($root . '/wp-load.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        fwrite(STDERR, "studio-staging-diagnostic: wp-load.php not found above " . __DIR__ . "\n");
        exit(1);
    }
    $root = $parent;
}

define('WP_USE_THEMES', false);
require $root . '/wp-load.php';

echo json_encode(
    \QSD\Platform\Modules\StudioHost\StudioStagingDiagnostic::report(),
    JSON_PRETTY_PRINT
), PHP_EOL;
