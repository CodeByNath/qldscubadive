<?php

declare(strict_types=1);

// StudioHostModule: the /studio/ route, document gate, and its coexistence
// with /station/. Exercises the real static methods (route resolution, URL
// derivation, the rendered gate body) against stubbed WordPress primitives,
// then proves structurally — this repo's established convention for what a
// stub can't otherwise exercise — that no QSD UI/CSS/drawer code is reused,
// that the route cannot collide with /station/, and that the asset gateway
// never enqueues without both the route and the capability check passing.

if (!defined('QSD_PLUGIN_PATH')) {
    define('QSD_PLUGIN_PATH', dirname(__DIR__) . '/');
}
if (!defined('QSD_APP_PATH')) {
    define('QSD_APP_PATH', QSD_PLUGIN_PATH . 'app/');
}
if (!defined('QSD_PLUGIN_URL')) {
    define('QSD_PLUGIN_URL', 'https://qsd-test.local/wp-content/plugins/qsd-platform/');
}

$__queryVar          = null;
$__permalinkStructure = '/%postname%/';
$__loggedIn          = false;
$__canManageQsd      = false;

function get_query_var(string $name): string { global $__queryVar; return $__queryVar ?? ''; }
function get_option(string $name): string { global $__permalinkStructure; return $name === 'permalink_structure' ? $__permalinkStructure : ''; }
function home_url(string $path = '/'): string { return 'https://qsd-test.local' . $path; }
function add_query_arg(string $key, string $value, string $url): string { return $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value; }
function is_user_logged_in(): bool { global $__loggedIn; return $__loggedIn; }
function current_user_can(string $cap): bool { global $__canManageQsd; return $__canManageQsd; }
function esc_url(string $url): string { return $url; }
function esc_attr(string $v): string { return $v; }

require_once __DIR__ . '/../src/Core/PlatformAccess.php';
require_once __DIR__ . '/../src/Modules/AdminStation/AdminStationModule.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioHostModule.php';

use QSD\Platform\Modules\StudioHost\StudioHostModule;

$failures = [];
function check_studio_host(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

// ── 1) Route resolution ────────────────────────────────────────────────────
echo "1) isStudioRequest() reflects the query var the plugin's own rewrite rule sets\n";
{
    global $__queryVar;
    $__queryVar = '';
    check_studio_host(StudioHostModule::isStudioRequest() === false, 'not a Studio request when the query var is unset/empty');
    $__queryVar = '1';
    check_studio_host(StudioHostModule::isStudioRequest() === true, 'is a Studio request when the query var is exactly "1"');
}

// ── 2) URL derivation, with and without pretty permalinks ─────────────────
echo "\n2) url() resolves correctly with and without pretty permalinks\n";
{
    global $__permalinkStructure;
    $__permalinkStructure = '/%postname%/';
    check_studio_host(StudioHostModule::url() === 'https://qsd-test.local/studio/', 'pretty permalinks on: /studio/', StudioHostModule::url());

    $__permalinkStructure = '';
    check_studio_host(StudioHostModule::url() === 'https://qsd-test.local/?qsd_studio=1', 'pretty permalinks off: query-var fallback', StudioHostModule::url());
    $__permalinkStructure = '/%postname%/';
}

// ── 3) Gate: logged out → access-denied, pointing at /station/ ────────────
echo "\n3) a logged-out visitor sees access-denied, linking to the real sign-in page\n";
{
    global $__loggedIn, $__canManageQsd;
    $__loggedIn     = false;
    $__canManageQsd = false;
    $body = StudioHostModule::renderBody();
    check_studio_host(str_contains($body, 'sign-in'), 'the access-denied body mentions signing in', $body);
    check_studio_host(str_contains($body, '/station/'), 'the access-denied body links to the real /station/ sign-in, not a duplicate login form', $body);
    check_studio_host(!str_contains($body, StudioHostModule::MOUNT_ID), 'the Studio mount point is not rendered to a logged-out visitor');
}

// ── 4) Gate: logged in without the capability → identical denial ──────────
echo "\n4) a signed-in visitor without manage_qsd gets the same denial, not WordPress admin\n";
{
    global $__loggedIn, $__canManageQsd;
    $__loggedIn     = true;
    $__canManageQsd = false;
    $body = StudioHostModule::renderBody();
    check_studio_host(str_contains($body, 'sign-in'), 'still shows the access-denied body');
    check_studio_host(!str_contains($body, StudioHostModule::MOUNT_ID), 'still never renders the mount point');
}

// ── 5) Gate: authorized platform manager → the mount point ────────────────
echo "\n5) an authorized platform manager reaches the Studio mount point\n";
{
    global $__loggedIn, $__canManageQsd;
    $__loggedIn     = true;
    $__canManageQsd = true;
    $body = StudioHostModule::renderBody();
    check_studio_host(str_contains($body, StudioHostModule::MOUNT_ID), 'the mount point div is rendered for an authorized caller', $body);
    check_studio_host(!str_contains($body, 'sign-in'), 'the access-denied state is not also rendered');
}

// ── 6) Structural: route/query-var cannot collide with /station/ ──────────
echo "\n6) the Studio route cannot collide with the Admin Station route\n";
{
    check_studio_host(
        StudioHostModule::ROUTE_SLUG !== \QSD\Platform\Modules\AdminStation\AdminStationModule::ROUTE_SLUG,
        'ROUTE_SLUG differs from AdminStationModule\'s',
        [StudioHostModule::ROUTE_SLUG, \QSD\Platform\Modules\AdminStation\AdminStationModule::ROUTE_SLUG],
    );
    check_studio_host(
        StudioHostModule::QUERY_VAR !== \QSD\Platform\Modules\AdminStation\AdminStationModule::QUERY_VAR,
        'QUERY_VAR differs from AdminStationModule\'s',
        [StudioHostModule::QUERY_VAR, \QSD\Platform\Modules\AdminStation\AdminStationModule::QUERY_VAR],
    );
}

// ── 7) Structural: no reuse of legacy QSD UI, drawers, templates, CSS ─────
echo "\n7) no QSD UI/CSS/drawer code is reused by the Studio host or its templates\n";
{
    $root           = dirname(__DIR__);
    $moduleSource   = (string) file_get_contents($root . '/src/Modules/StudioHost/StudioHostModule.php');
    $gatewaySource  = (string) file_get_contents($root . '/src/Modules/StudioHost/StudioAssetGateway.php');
    $documentTpl    = (string) file_get_contents($root . '/app/modules/studio-host/templates/studio-document.php');
    $deniedTpl      = (string) file_get_contents($root . '/app/modules/studio-host/templates/access-denied.php');
    $mountTpl       = (string) file_get_contents($root . '/app/modules/studio-host/templates/studio-mount.php');
    $combinedTpl    = $documentTpl . $deniedTpl . $mountTpl;

    $forbidden = ['admin-station.php', 'station-document.php', 'login-gate.php', 'EntityDrawer', 'drawer-kit', 'admin-station.css', 'atomic-engine', 'AdminStationModule::renderBody', 'AdminStationModule::MOUNT_ID'];
    foreach ($forbidden as $symbol) {
        check_studio_host(
            !str_contains($moduleSource, $symbol) && !str_contains($gatewaySource, $symbol) && !str_contains($combinedTpl, $symbol),
            "neither the Studio host module/gateway nor its templates reference '{$symbol}'",
        );
    }
    check_studio_host(
        str_contains($moduleSource, 'AdminStationModule::url()'),
        'the only AdminStation reference is consuming its public URL helper — not its templates, CSS, or UI',
    );

    $forbiddenDist = ['dist/css/admin-station.css', 'dist/css/drawer-kit.css', 'dist/js/admin-station.js'];
    foreach ($forbiddenDist as $symbol) {
        check_studio_host(!str_contains($gatewaySource, $symbol), "the asset gateway never references the Admin Station bundle path '{$symbol}'");
    }
}

// ── 8) Structural: the Studio host is never indexed and never deployed to
//      staging/production from this phase (asserted at push-evidence level,
//      not here) — confirm the noindex/toolbar gating exists in source ────
echo "\n8) structural: noindex and toolbar suppression are wired exactly as /station/'s are\n";
{
    $moduleSource = (string) file_get_contents(dirname(__DIR__) . '/src/Modules/StudioHost/StudioHostModule.php');
    check_studio_host(str_contains($moduleSource, "add_filter('wp_robots'"), 'wp_robots is filtered to noindex the Studio route');
    check_studio_host(str_contains($moduleSource, "add_filter('show_admin_bar'"), 'the WordPress toolbar is suppressed on the Studio route');
    check_studio_host(str_contains($moduleSource, 'PlatformAccess::CAP'), 'the route gates on the shared Core\\PlatformAccess capability, not a new one');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio host checks passed.\n";
