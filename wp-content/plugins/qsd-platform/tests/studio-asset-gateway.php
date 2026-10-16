<?php

declare(strict_types=1);

// StudioAssetGateway: the versioned WEX asset delivery seam. Exercises the
// real class with a real StudioReleaseLock behind a stub transport (never a
// mock, never the network) — proving route/capability gating, the end-to-end
// enqueue of a release the lock already verified (with its SRI digest on the
// resulting tag), and that the host works with nothing enqueued until a
// release can be verified, exactly like the old missing-manifest state.

$__isStudioRoute   = false;
$__loggedIn        = false;
$__canManageQsd    = false;
$__enqueuedStyles  = [];
$__enqueuedScripts = [];
$__inlineScripts   = [];
$__logoUrl         = null;
$__transients      = [];

function is_user_logged_in(): bool { global $__loggedIn; return $__loggedIn; }
function current_user_can(string $cap): bool { global $__canManageQsd; return $__canManageQsd; }
function wp_enqueue_style(string $handle, string $src, array $deps, string $ver): void { global $__enqueuedStyles; $__enqueuedStyles[$handle] = [$src, $ver]; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = [], mixed $ver = false, bool $footer = false): void { global $__enqueuedScripts; $__enqueuedScripts[$handle] = [$src, $ver]; }
function wp_register_script(string $handle, mixed $src, array $deps, mixed $ver, bool $footer): void {}
// Real wp_add_inline_script() appends per handle (default position 'after');
// a bare overwrite here would silently drop the first of two calls on the
// same handle, which is exactly what StudioAssetGateway now does.
function wp_add_inline_script(string $handle, string $data): void { global $__inlineScripts; $__inlineScripts[$handle] = ($__inlineScripts[$handle] ?? '') . $data; }
function rest_url(string $path): string { return 'https://qsd-test.local/wp-json/' . $path; }
function wp_create_nonce(string $action): string { return 'nonce-' . $action; }
function esc_url_raw(string $url): string { return $url; }
function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
function wp_json_encode(mixed $value): string|false { return json_encode($value); }
function get_transient(string $key): mixed { global $__transients; return $__transients[$key] ?? false; }
function set_transient(string $key, mixed $value, int $ttl): bool { global $__transients; $__transients[$key] = $value; return true; }

require_once __DIR__ . '/../src/Core/PlatformAccess.php';
require_once __DIR__ . '/../src/Modules/Account/Support/AccountMedia.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioHostModule.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetPath.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioReleaseLock.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetGateway.php';

// isStudioRequest() is gated internally on get_query_var(); stub it here
// rather than in StudioHostModule itself.
function get_query_var(string $name): string { global $__isStudioRoute; return $__isStudioRoute ? '1' : ''; }

use QSD\Platform\Modules\StudioHost\StudioAssetGateway;
use QSD\Platform\Modules\StudioHost\StudioReleaseLock;

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

/**
 * A real StudioReleaseLock, pinned at a fixture release and wired to a stub
 * transport returning a fixture manifest body whose digest the pin expects
 * — the same shape WEX's real closeout manifest has (contract, entry,
 * styles, releaseId, files inventory of sha384 digests).
 *
 * @param array<string, mixed> $manifestOverrides
 * @return array{lock: StudioReleaseLock, pin: array<string, string>, calls: array<int, string>}
 */
function make_fixture_lock(array $manifestOverrides = [], ?int $httpStatus = 200): array
{
    $manifest = array_merge([
        'contract'  => 'wex-studio-host@1',
        'entry'     => 'assets/studio.js',
        'styles'    => ['assets/studio.css'],
        'version'   => 'fixture-release-1',
        'releaseId' => 'fixture-release-1',
        'sourceSha' => 'deadbeef',
        'files'     => [
            'assets/studio.js'  => 'sha384-' . base64_encode(hash('sha384', 'script bytes', true)),
            'assets/studio.css' => 'sha384-' . base64_encode(hash('sha384', 'style bytes', true)),
        ],
    ], $manifestOverrides);
    $body = (string) json_encode($manifest);

    $pin = [
        'releaseId'              => 'fixture-release-1',
        'manifestUrl'            => 'https://wex-fixture.test/releases/fixture-release-1/manifest.json',
        'expectedManifestDigest' => 'sha384-' . base64_encode(hash('sha384', $body, true)),
        'expectedContract'       => 'wex-studio-host@1',
    ];

    $calls = [];
    $transport = function (string $url) use (&$calls, $body, $httpStatus): array {
        $calls[] = $url;
        return ['status' => $httpStatus, 'body' => $httpStatus === 200 ? $body : null];
    };

    return ['lock' => new StudioReleaseLock($pin, $transport), 'pin' => $pin, 'calls' => &$calls];
}

/** Pulls one `<prefix> = { ... };` statement's JSON payload out of the concatenated inline script. */
function extract_inline_json(string $script, string $prefix): mixed
{
    $start = strpos($script, $prefix);
    if ($start === false) {
        return null;
    }
    $jsonStart = $start + strlen($prefix);
    $end = strpos($script, ';', $jsonStart);
    return $end === false ? null : json_decode(substr($script, $jsonStart, $end - $jsonStart), true);
}

// ── 1) Off the Studio route: nothing is ever enqueued, regardless of auth,
//      and the release lock is never even consulted ───────────────────────
echo "1) nothing is enqueued off the /studio/ route\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts, $__enqueuedStyles, $__logoUrl;
    $__isStudioRoute = false;
    $__loggedIn      = true;
    $__canManageQsd  = true;
    $__logoUrl       = null;
    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => $GLOBALS['__logoUrl'], $fixture['lock']);
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'no script enqueued when the request is not the Studio route');
    check_studio_asset($__enqueuedStyles === [], 'no style enqueued when the request is not the Studio route');
    check_studio_asset($fixture['calls'] === [], 'the release lock is never consulted off the Studio route');
}

// ── 2) On the route, unauthenticated/unauthorized: still nothing, and the
//      release lock is still never consulted ──────────────────────────────
echo "\n2) on the route, an unauthorized caller still gets nothing\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts;
    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => null, $fixture['lock']);

    $__isStudioRoute = true;
    $__loggedIn      = false;
    $__canManageQsd  = false;
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'logged-out on the Studio route still enqueues nothing');

    $__loggedIn     = true;
    $__canManageQsd = false; // signed in, but lacks manage_qsd
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'signed in without the platform capability still enqueues nothing');
    check_studio_asset($fixture['calls'] === [], 'the release lock is never consulted for an unauthorized caller');
}

// ── 3) Authorized, but the release lock cannot verify anything yet: host
//      still works, exactly like the old missing-local-manifest state ─────
echo "\n3) an authorized caller gets nothing enqueued while no release can be verified — the host does not depend on WEX's bundle existing\n";
{
    global $__isStudioRoute, $__loggedIn, $__canManageQsd, $__enqueuedScripts, $__enqueuedStyles, $__inlineScripts;
    $__isStudioRoute = true;
    $__loggedIn      = true;
    $__canManageQsd  = true;
    $__enqueuedScripts = [];
    $__enqueuedStyles  = [];
    $__inlineScripts   = [];

    $fixture = make_fixture_lock([], httpStatus: 503); // upstream unreachable/erroring
    $gateway = new StudioAssetGateway(static fn(): ?string => null, $fixture['lock']);
    $gateway->enqueue();
    check_studio_asset($__enqueuedScripts === [], 'no script enqueued when the release cannot be verified');
    check_studio_asset($__enqueuedStyles === [], 'no style enqueued when the release cannot be verified');
    check_studio_asset($__inlineScripts === [], 'the runtime config is not even written when there is nothing to boot');
}

// ── 4) Authorized, with a release the lock has fully verified ─────────────
echo "\n4) an authorized caller with a verified release gets the bundle enqueued with its SRI digest, scoped to /studio/\n";
{
    global $__enqueuedScripts, $__enqueuedStyles, $__inlineScripts, $__logoUrl;
    $__enqueuedScripts = [];
    $__enqueuedStyles  = [];
    $__inlineScripts   = [];
    $__logoUrl         = 'https://qsd-test.local/wp-content/uploads/qsd-account/abc123.png';

    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => $GLOBALS['__logoUrl'], $fixture['lock']);
    $gateway->enqueue();

    check_studio_asset(isset($__enqueuedScripts['qsd-studio-host']), 'the WEX entry script is enqueued under its own handle, distinct from qsd-admin-station');
    check_studio_asset(
        $__enqueuedScripts['qsd-studio-host'][0] === 'https://wex-fixture.test/releases/fixture-release-1/assets/studio.js',
        'the enqueued script URL is built from the verified release, not a plugin-local path',
        $__enqueuedScripts,
    );
    check_studio_asset($__enqueuedScripts['qsd-studio-host'][1] === 'fixture-release-1', 'the script version is the immutable release id, not a filemtime');
    check_studio_asset(isset($__enqueuedStyles['qsd-studio-host-0']), 'the WEX stylesheet is enqueued');

    // setScriptAttributes/setStyleAttributes read per-instance state populated by enqueue(); exercise the SAME instance.
    $scriptTag = $gateway->setScriptAttributes('<script src="x"></script>', 'qsd-studio-host');
    check_studio_asset(str_contains($scriptTag, 'type="module"'), 'the WEX entry script tag is marked type="module"');
    check_studio_asset(
        str_contains($scriptTag, 'integrity="sha384-' . base64_encode(hash('sha384', 'script bytes', true)) . '"'),
        'the WEX entry script tag carries the exact SRI digest the release lock verified',
        $scriptTag,
    );
    check_studio_asset(str_contains($scriptTag, 'crossorigin="anonymous"'), 'the WEX entry script tag is crossorigin for its external origin');

    $styleTag = $gateway->setStyleAttributes('<link rel="stylesheet" href="x">', 'qsd-studio-host-0');
    check_studio_asset(
        str_contains($styleTag, 'integrity="sha384-' . base64_encode(hash('sha384', 'style bytes', true)) . '"') && str_contains($styleTag, 'crossorigin="anonymous"'),
        'the WEX stylesheet tag carries its own verified SRI digest and crossorigin',
        $styleTag,
    );
    check_studio_asset(
        $gateway->setScriptAttributes('<script src="x"></script>', 'some-other-handle') === '<script src="x"></script>',
        'a handle this gateway did not enqueue from the verified release is never given an integrity attribute',
    );

    check_studio_asset(isset($__inlineScripts['qsd-studio-config']), 'the runtime config is written once a verified release is enqueued');
    $configJson = extract_inline_json($__inlineScripts['qsd-studio-config'], 'window.QSDStudioConfig = ');
    check_studio_asset(
        is_array($configJson) && str_ends_with((string) $configJson['apiRoot'], '/qsd/v1/studio/'),
        'the runtime config is scoped to the Studio API root, not the full qsd/v1 business root',
        $configJson,
    );
    check_studio_asset(
        !str_contains($__inlineScripts['qsd-studio-config'], 'QSDConfig ='),
        'the Studio config is its own window global, never overwriting window.QSDConfig',
    );

    $hostOptions = extract_inline_json($__inlineScripts['qsd-studio-config'], 'globalThis.wexStudioHostOptions = ');
    check_studio_asset(
        is_array($hostOptions) && ($hostOptions['logoImageBinding'] ?? null) === ['source' => 'profile.logo', 'target' => 'imageUrl'],
        'the WEX host options declare the profile.logo -> imageUrl binding',
        $hostOptions,
    );
    check_studio_asset(
        is_array($hostOptions) && ($hostOptions['sourceValues']['profile.logo'] ?? null) === $__logoUrl,
        "the WEX host options carry the authorised Account Brand logo URL, not a hardcoded fixture",
        $hostOptions,
    );
    check_studio_asset(count($fixture['calls']) === 1, 'the release lock is consulted exactly once for this request');
}

// ── 4b) No Brand logo published yet: the handoff carries null, not a guess ─
echo "\n4b) no published Brand logo yields a null sourceValue, never a fabricated URL\n";
{
    global $__inlineScripts, $__logoUrl;
    $__inlineScripts = [];
    $__logoUrl = null;
    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => $GLOBALS['__logoUrl'], $fixture['lock']);
    $gateway->enqueue();

    $hostOptions = extract_inline_json($__inlineScripts['qsd-studio-config'], 'globalThis.wexStudioHostOptions = ');
    check_studio_asset(
        is_array($hostOptions) && array_key_exists('profile.logo', $hostOptions['sourceValues'] ?? []) && $hostOptions['sourceValues']['profile.logo'] === null,
        'with no Brand logo configured, profile.logo resolves to null rather than being omitted or fabricated',
        $hostOptions,
    );
}

// ── 4c) A value wrapped in Markdown link syntax is rejected to null, not
//      passed through — the exact malformed shape Reviewer found live in
//      the browser (`[https://...](https://...webp "https://...webp")`),
//      which a bare `filter_var(..., FILTER_VALIDATE_URL)` would miss ─────
echo "\n4c) a Markdown-wrapped logo URL is rejected to null, never passed through to WEX\n";
{
    global $__inlineScripts, $__logoUrl;
    $__inlineScripts = [];
    $clean     = 'https://qsd-test.local/wp-content/uploads/qsd-account/' . str_repeat('a1', 32) . '.webp';
    $__logoUrl = '[' . $clean . '](' . $clean . ' "' . $clean . '")';
    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => $GLOBALS['__logoUrl'], $fixture['lock']);
    $gateway->enqueue();

    $hostOptions = extract_inline_json($__inlineScripts['qsd-studio-config'], 'globalThis.wexStudioHostOptions = ');
    check_studio_asset(
        is_array($hostOptions) && array_key_exists('profile.logo', $hostOptions['sourceValues'] ?? []) && $hostOptions['sourceValues']['profile.logo'] === null,
        'a value wrapped in Markdown link syntax resolves to null, not the malformed string, and not an exception',
        $hostOptions,
    );
}

// ── 4d) A clean, well-formed logo URL still passes through unchanged ─────
echo "\n4d) a clean, well-formed logo URL still passes through unchanged\n";
{
    global $__inlineScripts, $__logoUrl;
    $__inlineScripts = [];
    $__logoUrl = 'https://qsd-test.local/wp-content/uploads/qsd-account/' . str_repeat('a1', 32) . '.webp';
    $fixture = make_fixture_lock();
    $gateway = new StudioAssetGateway(static fn(): ?string => $GLOBALS['__logoUrl'], $fixture['lock']);
    $gateway->enqueue();

    $hostOptions = extract_inline_json($__inlineScripts['qsd-studio-config'], 'globalThis.wexStudioHostOptions = ');
    check_studio_asset(
        is_array($hostOptions) && ($hostOptions['sourceValues']['profile.logo'] ?? null) === $__logoUrl,
        'a clean, well-formed https logo URL is handed to WEX unchanged',
        $hostOptions,
    );
}

// ── 5) The real, no-arg constructor reads the actual Account Brand logo URL,
//      not just the test-seam resolver exercised above (release lock is
//      still a fixture — this case is about the logo resolver default only) ─
echo "\n5) the default resolver reads the real Account Brand projection (AccountRepository + AccountMedia)\n";
{
    global $__inlineScripts;

    function wp_upload_dir(): array
    {
        return ['baseurl' => 'https://qsd-test.local/wp-content/uploads', 'basedir' => sys_get_temp_dir() . '/qsd-studio-asset-uploads'];
    }
    function trailingslashit(string $path): string { return rtrim($path, '/') . '/'; }

    require_once __DIR__ . '/support-account-repository.php';
    require_once __DIR__ . '/../src/PlatformIdentifier/PlatformIdentifierPolicy.php';
    require_once __DIR__ . '/../src/Modules/Account/Support/AccountSchema.php';
    require_once __DIR__ . '/../src/Modules/Account/Support/AccountRepository.php';

    $table = new AccountOptionsTable();
    $table->rows[\QSD\Platform\Modules\Account\Support\AccountSchema::OPTION_KEY] = json_encode([
        'brand' => ['name' => 'Queensland Scuba Diving', 'code' => 'QSD', 'logo_media_id' => 'deadbeef.png', 'favicon_media_id' => null],
    ]);
    $GLOBALS['wpdb'] = new AccountWpdb($table);

    $__inlineScripts = [];
    $fixture = make_fixture_lock();
    $defaultGateway = new StudioAssetGateway(null, $fixture['lock']);
    $defaultGateway->enqueue();

    $hostOptions = extract_inline_json($__inlineScripts['qsd-studio-config'] ?? '', 'globalThis.wexStudioHostOptions = ');
    check_studio_asset(
        is_array($hostOptions)
            && $hostOptions['sourceValues']['profile.logo'] === 'https://qsd-test.local/wp-content/uploads/qsd-account/deadbeef.png',
        'the default (production) resolver reads the real Brand logo_media_id through AccountRepository/AccountMedia, not a fixture value',
        $hostOptions,
    );
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio asset gateway checks passed.\n";
