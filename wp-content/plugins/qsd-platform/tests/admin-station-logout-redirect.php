<?php

declare(strict_types=1);

// Admin Station logout destination and route URL.
//
// The Admin Station header's Log out action uses window.QSDConfig.logoutUrl,
// written by Core\AssetLoader. It must:
//   - return to the Admin Station's own server-derived URL (which then shows
//     the login gate) — never wp-login.php, never wp-admin, never a URL taken
//     from the raw request;
//   - be HTML-decoded, because wp_nonce_url() emits `&amp;` and the value is
//     assigned as a DOM href from JavaScript where nothing decodes it;
//   - resolve with and without pretty permalinks.
//
// AssetLoader's private logoutUrl() is exercised directly via reflection
// against minimal WordPress stubs.

$__permalinkStructure = '/%postname%/';

function home_url(string $path = '/'): string { return 'https://qsd-test.local' . $path; }
function get_option(string $key, mixed $default = false): mixed
{
    global $__permalinkStructure;
    return $key === 'permalink_structure' ? $__permalinkStructure : $default;
}
function add_query_arg(string $key, string $value, string $url): string
{
    return $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value;
}
function esc_url_raw(string $url): string { return $url; }
function wp_specialchars_decode(string $text, int $quote = ENT_NOQUOTES): string
{
    return htmlspecialchars_decode($text, $quote);
}
// Mirrors core: wp_logout_url() → wp_nonce_url(), which HTML-encodes `&`.
function wp_logout_url(string $redirect = ''): string
{
    return 'https://qsd-test.local/wp-login.php?action=logout&amp;_wpnonce=abc123&amp;redirect_to=' . rawurlencode($redirect);
}

require_once __DIR__ . '/autoload.php';

use QSD\Platform\Core\AssetLoader;
use QSD\Platform\Modules\AdminStation\AdminStationModule;

$failures = [];
function check_logout(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

$logoutUrl = new ReflectionMethod(AssetLoader::class, 'logoutUrl');
$loader    = new AssetLoader();

echo "1) the Admin Station URL\n";
check_logout(AdminStationModule::url() === 'https://qsd-test.local/station/', 'pretty permalinks resolve to /station/', AdminStationModule::url());
$__permalinkStructure = '';
check_logout(AdminStationModule::url() === 'https://qsd-test.local/?qsd_station=1', 'plain permalinks fall back to the query-var form', AdminStationModule::url());
$__permalinkStructure = '/%postname%/';

echo "\n2) the logout URL\n";
$url = $logoutUrl->invoke($loader);
check_logout(!str_contains($url, '&amp;'), 'the HTML-encoded &amp; from wp_nonce_url() is decoded for JavaScript use', $url);
check_logout(str_contains($url, '&_wpnonce=abc123'), 'the logout nonce survives as a real query parameter', $url);
check_logout(
    str_contains($url, 'redirect_to=' . rawurlencode('https://qsd-test.local/station/')),
    'logout returns to the Admin Station, which then shows its login gate',
    $url,
);
check_logout(!str_contains($url, 'wp-admin'), 'logout never lands on wp-admin', $url);

echo "\n3) structural\n";
$source = (string) file_get_contents(dirname(__DIR__) . '/src/Core/AssetLoader.php');
check_logout(
    str_contains($source, 'wp_logout_url(AdminStationModule::url())') && !str_contains($source, 'REQUEST_URI'),
    'the destination is AdminStationModule::url(), never the raw request path',
);
check_logout(
    str_contains($source, 'if (!AdminStationModule::isStationRequest())'),
    'no platform asset loads outside the /station/ route',
);

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nAdmin Station logout redirect checks passed.\n";
