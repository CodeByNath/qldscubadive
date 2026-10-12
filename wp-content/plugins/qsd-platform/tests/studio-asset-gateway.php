<?php

declare(strict_types=1);

// StudioAssetGateway: the versioned WEX-only asset delivery seam. Exercises
// the real class against a real fixture `studio-assets/` directory (QSD_PLUGIN_PATH
// points at a temp fixture root for this process only, never the real plugin
// tree), proving route/capability gating and that the host works with no
// manifest supplied yet, without touching any real QSD bundle path.

$__fixtureRoot = sys_get_temp_dir() . '/qsd-studio-asset-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($__fixtureRoot . 'studio-assets', 0755, true);

define('QSD_PLUGIN_PATH', $__fixtureRoot);
define('QSD_PLUGIN_URL', 'https://qsd-test.local/wp-content/plugins/qsd-platform/');

$__isStudioRoute  = false;
$__loggedIn       = false;
$__canManageQsd   = false;
$__enqueuedStyles = [];
$__enqueuedScripts = [];
$__inlineScripts  = [];

function is_user_logged_in(): bool { global $__loggedIn; return $__loggedIn; }
function current_user_can(string $cap): bool { global $__canManageQsd; return $__canManageQsd; }
function wp_enqueue_style(string $handle, string $src, array $deps, string $ver): void { global $__enqueuedStyles; $__enqueuedStyles[$handle] = [$src, $ver]; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = [], mixed $ver = false, bool $footer = false): void { global $__enqueuedScripts; $__enqueuedScripts[$handle] = [$src, $ver]; }
function wp_register_script(string $handle, mixed $src, array $deps, mixed $ver, bool $footer): void {}
function wp_add_inline_script(string $handle, string $data): void { global $__inlineScripts; $__inlineScripts[$handle] = $data; }
function rest_url(string $path): string { return 'https://qsd-test.local/wp-json/' . $path; }
function wp_create_nonce(string $action): string { return 'nonce-' . $action; }
function esc_url_raw(string $url): string { return $url; }
function wp_json_encode(mixed $value): string|false { return json_encode($value); }

require_once __DIR__ . '/../src/Core/PlatformAccess.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioHostModule.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetGateway.php';

// isStudioRequest() is gated internally on get_query_var(); stub it here
// rather than in StudioHostModule itself.
function get_query_var(string $name): string { global $__isStudioRoute; return $__isStudioRoute ? '1' : ''; }

use QSD\Platform\Modules\StudioHost\StudioAssetGateway;

$failures = [];
function check_studio_asset(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

$gateway = new StudioAssetGateway();

// ── 1) Off the Studio route: nothing is ever enqueued, regardless of auth ─
echo "1) nothing is enqueued off the /studio/ route\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts, $__enqueuedStyles;
    $__isStudioRoute = false;
    $__loggedIn      = true;
    $__canManageQsd  = true;
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'no script enqueued when the request is not the Studio route');
    check_studio_asset($__enqueuedStyles === [], 'no style enqueued when the request is not the Studio route');
}

// ── 2) On the route, unauthenticated/unauthorized: still nothing ──────────
echo "\n2) on the route, an unauthorized caller still gets nothing\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts;
    $__isStudioRoute = true;
    $__loggedIn      = false;
    $__canManageQsd  = false;
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'logged-out on the Studio route still enqueues nothing');

    $__loggedIn     = true;
    $__canManageQsd = false; // signed in, but lacks manage_qsd
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'signed in without the platform capability still enqueues nothing');
}

// ── 3) Authorized, but WEX has not supplied a manifest yet: host still works ─
echo "\n3) an authorized caller with no WEX manifest yet enqueues nothing — the host does not depend on WEX's bundle existing\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts, $__enqueuedStyles, $__inlineScripts;
    $__isStudioRoute = true;
    $__loggedIn      = true;
    $__canManageQsd  = true;
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'no script enqueued when manifest.json is absent');
    check_studio_asset($__enqueuedStyles === [], 'no style enqueued when manifest.json is absent');
    check_studio_asset($__inlineScripts === [], 'the runtime config is not even written when there is nothing to boot');
}

// ── 4) Authorized, with a real WEX-supplied manifest + bundle ─────────────
echo "\n4) an authorized caller with a real manifest gets the bundle enqueued, scoped to /studio/\n";
{
    global $__fixtureRoot, $__enqueuedScripts, $__enqueuedStyles, $__inlineScripts;
    file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode([
        'entry'   => 'studio.js',
        'styles'  => ['studio.css'],
        'version' => '1.0.0',
    ]));
    file_put_contents($__fixtureRoot . 'studio-assets/studio.js', '// wex bundle');
    file_put_contents($__fixtureRoot . 'studio-assets/studio.css', '/* wex styles */');

    $gateway->enqueue();

    check_studio_asset(isset($__enqueuedScripts['qsd-studio-host']), 'the WEX entry script is enqueued under its own handle, distinct from qsd-admin-station');
    check_studio_asset(
        $__enqueuedScripts['qsd-studio-host'][0] === 'https://qsd-test.local/wp-content/plugins/qsd-platform/studio-assets/studio.js',
        'the enqueued script URL points at studio-assets/, not dist/',
        $__enqueuedScripts['qsd-studio-host'],
    );
    check_studio_asset(isset($__enqueuedStyles['qsd-studio-host-0']), 'the WEX stylesheet is enqueued');
    check_studio_asset(isset($__inlineScripts['qsd-studio-config']), 'the runtime config is written once a real bundle is enqueued');
    $configJson = json_decode(substr($__inlineScripts['qsd-studio-config'], strlen('window.QSDStudioConfig = '), -1), true);
    check_studio_asset(
        is_array($configJson) && str_ends_with((string) $configJson['apiRoot'], '/qsd/v1/studio/'),
        'the runtime config is scoped to the Studio API root, not the full qsd/v1 business root',
        $configJson,
    );
    check_studio_asset(
        !str_contains($__inlineScripts['qsd-studio-config'], 'QSDConfig ='),
        'the Studio config is its own window global, never overwriting window.QSDConfig',
    );
}

// ── 5) A manifest entry that tries to escape studio-assets/ is refused ────
echo "\n5) a manifest entry path cannot escape the studio-assets/ root\n";
{
    global $__fixtureRoot, $__enqueuedScripts;
    $__enqueuedScripts = [];
    mkdir($__fixtureRoot . 'secret', 0755, true);
    file_put_contents($__fixtureRoot . 'secret/outside.js', '// should never be reachable');
    file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode(['entry' => '../secret/outside.js']));

    $gateway->enqueue();
    check_studio_asset(!isset($__enqueuedScripts['qsd-studio-host']), 'an entry path resolving outside studio-assets/ is never enqueued as the WEX bundle', $__enqueuedScripts);
}

// ── 6) A sibling directory whose name merely starts with "studio-assets"
//      must not pass containment by string prefix alone ──────────────────
echo "\n6) a sibling directory like studio-assets-evil/ cannot pass as contained by a bare string prefix match\n";
{
    global $__fixtureRoot, $__enqueuedScripts;
    $__enqueuedScripts = [];
    mkdir($__fixtureRoot . 'studio-assets-evil', 0755, true);
    file_put_contents($__fixtureRoot . 'studio-assets-evil/secret2.js', '// should never be reachable either');
    file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode(['entry' => '../studio-assets-evil/secret2.js']));

    $gateway->enqueue();
    check_studio_asset(
        !isset($__enqueuedScripts['qsd-studio-host']),
        'an entry resolving into a sibling "studio-assets-evil/" directory is rejected, not accepted by a bare prefix match',
        $__enqueuedScripts,
    );
}

// ── 7) A symlink planted inside studio-assets/ pointing outside it ────────
echo "\n7) a symlink inside studio-assets/ that points outside it is refused, not followed\n";
{
    global $__fixtureRoot, $__enqueuedScripts;
    $__enqueuedScripts = [];
    file_put_contents($__fixtureRoot . 'outside-secret.js', '// should never be reachable via a symlink either');
    @unlink($__fixtureRoot . 'studio-assets/escape-link.js');
    $linked = @symlink($__fixtureRoot . 'outside-secret.js', $__fixtureRoot . 'studio-assets/escape-link.js');
    file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode(['entry' => 'escape-link.js']));

    if (!$linked) {
        echo "  skip — this filesystem/environment does not support creating symlinks\n";
    } else {
        $gateway->enqueue();
        check_studio_asset(
            !isset($__enqueuedScripts['qsd-studio-host']),
            'a symlink resolving outside studio-assets/ is rejected, never enqueued as the WEX bundle',
            $__enqueuedScripts,
        );
    }
}

// ── 8) URL-unsafe manifest entry forms are rejected before any filesystem
//      check — an absolute path, a Windows-style path, and a percent-encoded
//      form must never reach either the real-path check or the browser URL ─
echo "\n8) absolute, Windows-style, and percent-encoded manifest entries are all rejected\n";
{
    global $__fixtureRoot, $__enqueuedScripts;

    $unsafeEntries = [
        'an absolute path'        => '/etc/passwd',
        'a Windows drive path'    => 'C:\\Windows\\System32\\config.sys',
        'a bare backslash form'   => 'studio-assets\\..\\secret.js',
        'a percent-encoded form'  => '%2e%2e%2fsecret.js',
        'a literal dot segment'   => './studio.js/../../secret.js',
    ];

    foreach ($unsafeEntries as $label => $entry) {
        $__enqueuedScripts = [];
        file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode(['entry' => $entry]));
        $gateway->enqueue();
        check_studio_asset(!isset($__enqueuedScripts['qsd-studio-host']), "{$label} ('{$entry}') is rejected outright, never enqueued", $__enqueuedScripts);
    }

    // A safe entry still works after all those rejections — the validator
    // does not over-reject plain, legitimate relative paths.
    $__enqueuedScripts = [];
    file_put_contents($__fixtureRoot . 'studio-assets/manifest.json', json_encode(['entry' => 'studio.js']));
    $gateway->enqueue();
    check_studio_asset(isset($__enqueuedScripts['qsd-studio-host']), 'a plain, legitimate relative entry still enqueues correctly after the stricter validation');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio asset gateway checks passed.\n";
