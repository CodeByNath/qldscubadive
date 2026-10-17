<?php

/**
 * CLI-only entry point for `StudioStagingPointer::inspectStoredState()` —
 * printed as JSON to stdout. Unlike `studio-staging-diagnostic.php`, this
 * calls neither `resolvePin()` nor `StudioStagingDiagnostic::report()`: it
 * performs exactly one `get_option()` read of the durable pointer record,
 * with no outbound pointer fetch, no manifest verification and no
 * `update_option()` call on any path. It calls that same read twice in one
 * process and reports whether the two reads matched, as direct evidence
 * (not merely an assertion) that nothing was written in between.
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
        fwrite(STDERR, "studio-staging-pointer-state: wp-load.php not found above " . __DIR__ . "\n");
        exit(1);
    }
    $root = $parent;
}

define('WP_USE_THEMES', false);
require $root . '/wp-load.php';

$pointer = new \QSD\Platform\Modules\StudioHost\StudioStagingPointer();
$before = $pointer->inspectStoredState();
$after = $pointer->inspectStoredState();

echo json_encode([
    'executedAt'  => gmdate('Y-m-d\TH:i:s\Z'),
    'state'       => $before,
    'nonMutating' => $before === $after,
], JSON_PRETTY_PRINT), PHP_EOL;
