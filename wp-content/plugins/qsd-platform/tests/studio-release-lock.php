<?php

declare(strict_types=1);

// StudioReleaseLock: the QSD-owned pin for exactly one verified, immutable
// WEX Studio release. Every case here exercises the real class against a
// stub transport (never the network) — proving the lock fails closed on a
// digest mismatch, an unexpected contract/release id, a malformed digest
// inventory, or an entry/style missing from that inventory, and that
// switching the active pin (forward, or back to a retained approved one)
// works with no asset copy, just a different pin value.

$__transients = [];
function get_transient(string $key): mixed { global $__transients; return $__transients[$key] ?? false; }
function set_transient(string $key, mixed $value, int $ttl): bool { global $__transients; $__transients[$key] = $value; return true; }

// Only exercised by section 11 below, which constructs a real
// StudioStagingPointer collaborator with `env: 'staging'`; every other
// section here passes an explicit pin, so StudioStagingPointer (and these
// two functions) are never touched.
$__options = [];
function get_option(string $key, mixed $default = false): mixed { global $__options; return array_key_exists($key, $__options) ? $__options[$key] : $default; }
function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool { global $__options; $__options[$key] = $value; return true; }

require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetPath.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioManifestVerifier.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioStagingPointer.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioReleaseLock.php';

use QSD\Platform\Modules\StudioHost\StudioReleaseLock;
use QSD\Platform\Modules\StudioHost\StudioStagingPointer;

$failures = [];
function check_release_lock(bool $condition, string $label, mixed $detail = null): void
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
 * @param array<string, mixed> $manifestOverrides Applied on top of a valid fixture manifest.
 * @param array<string, string> $pinOverrides Applied on top of the pin that matches the (unmodified) fixture manifest.
 * @return array{lock: StudioReleaseLock, body: string, pin: array<string, string>, calls: int}
 */
// Each call gets its own manifest URL (hence its own cache key) unless the
// caller explicitly pins one — the transient cache is a real global, shared
// across every case in this process, so two unrelated cases must never
// collide on the same cache entry.
$__buildLockCallCount = 0;

function build_lock(array $manifestOverrides = [], array $pinOverrides = [], ?int $httpStatus = 200, ?string $bodyOverride = null): array
{
    global $__buildLockCallCount;
    $__buildLockCallCount++;

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
    $body = $bodyOverride ?? (string) json_encode($manifest);

    $pin = array_merge([
        'releaseId'              => 'fixture-release-1',
        'manifestUrl'            => 'https://wex-fixture.test/releases/fixture-release-1/manifest-' . $__buildLockCallCount . '.json',
        'expectedManifestDigest' => 'sha384-' . base64_encode(hash('sha384', (string) json_encode($manifest), true)),
        'expectedContract'       => 'wex-studio-host@1',
    ], $pinOverrides);

    $calls = 0;
    $transport = function (string $url) use (&$calls, $body, $httpStatus): array {
        $calls++;
        return ['status' => $httpStatus, 'body' => $httpStatus === 200 ? $body : null];
    };

    $lock = new StudioReleaseLock($pin, $transport);
    return ['lock' => $lock, 'body' => $body, 'pin' => $pin, 'calls' => $calls];
}

// ── 1) A release matching every expectation verifies, with per-file digests
//      usable verbatim as an `integrity` attribute value ──────────────────
echo "1) a release matching the pin verifies in full\n";
{
    $fixture = build_lock();
    $release = $fixture['lock']->verifiedRelease();
    check_release_lock(is_array($release), 'a fully matching release verifies', $release);
    check_release_lock(($release['releaseId'] ?? null) === 'fixture-release-1', 'the verified result carries the pinned release id');
    check_release_lock(($release['entry'] ?? null) === 'assets/studio.js', 'the verified result carries the manifest entry');
    check_release_lock(($release['styles'] ?? null) === ['assets/studio.css'], 'the verified result carries the manifest styles');
    check_release_lock(
        preg_match('/^sha384-[A-Za-z0-9+\/]{64}=*$/', $release['files']['assets/studio.js'] ?? '') === 1,
        'the entry digest is a well-formed sha384 SRI string, directly usable as integrity="..."',
    );
    check_release_lock(
        $fixture['lock']->assetUrl($release['entry']) === 'https://wex-fixture.test/releases/fixture-release-1/assets/studio.js',
        'assetUrl() resolves a verified relative path against the pinned release directory',
    );
}

// ── 2) A manifest whose raw bytes don't match the pinned digest is rejected,
//      even though it parses as otherwise-valid JSON ──────────────────────
echo "\n2) a manifest digest mismatch fails closed\n";
{
    // A byte-for-byte valid manifest, but the pin still expects the ORIGINAL
    // fixture body's digest (tampered/stale content at the same URL).
    $tampered = build_lock(
        manifestOverrides: ['sourceSha' => 'tampered'],
        pinOverrides: ['expectedManifestDigest' => 'sha384-' . base64_encode(hash('sha384', 'not the real body', true))],
    );
    check_release_lock($tampered['lock']->verifiedRelease() === null, 'a manifest digest mismatch is rejected even though the body is valid JSON');
}

// ── 3) Unexpected contract or release id in an otherwise digest-valid
//      manifest is rejected — defence in depth beyond the raw digest check ─
echo "\n3) an unexpected contract or release id fails closed\n";
{
    $wrongContract = build_lock(['contract' => 'wex-studio-host@2']);
    check_release_lock($wrongContract['lock']->verifiedRelease() === null, 'an incompatible contract version is rejected');

    $wrongRelease = build_lock(['releaseId' => 'some-other-release']);
    check_release_lock($wrongRelease['lock']->verifiedRelease() === null, 'a manifest claiming a different release id than the pin is rejected');
}

// ── 4) A missing, empty, or malformed digest inventory is rejected ────────
echo "\n4) a missing, empty, or malformed digest inventory fails closed\n";
{
    $noFiles = build_lock(['files' => null]);
    check_release_lock($noFiles['lock']->verifiedRelease() === null, 'a manifest with no files inventory at all is rejected');

    $emptyFiles = build_lock(['files' => []]);
    check_release_lock($emptyFiles['lock']->verifiedRelease() === null, 'a manifest with an empty files inventory is rejected');

    $badDigestShape = build_lock(['files' => ['assets/studio.js' => 'not-a-real-digest', 'assets/studio.css' => 'sha384-' . base64_encode(hash('sha384', 'style bytes', true))]]);
    check_release_lock($badDigestShape['lock']->verifiedRelease() === null, 'a malformed (non-SRI-shaped) digest value is rejected');
}

// ── 5) entry/styles must themselves be present in the digest inventory ────
echo "\n5) an entry or style path missing from the digest inventory fails closed\n";
{
    $entryNotInFiles = build_lock(['entry' => 'assets/not-listed.js']);
    check_release_lock($entryNotInFiles['lock']->verifiedRelease() === null, 'an entry path absent from the files inventory is rejected');

    $styleNotInFiles = build_lock(['styles' => ['assets/not-listed.css']]);
    check_release_lock($styleNotInFiles['lock']->verifiedRelease() === null, 'a style path absent from the files inventory is rejected');
}

// ── 6) Unsafe inventory path forms are rejected — the same URL-safety check
//      StudioAssetGateway already requires, now guarding a URL build
//      instead of a filesystem read ─────────────────────────────────────
echo "\n6) absolute, Windows-style, percent-encoded, and traversal inventory keys are all rejected\n";
{
    $unsafeKeys = [
        'an absolute path'       => '/etc/passwd',
        'a Windows drive path'   => 'C:\\Windows\\System32\\config.sys',
        'a bare backslash form'  => 'assets\\..\\secret.js',
        'a percent-encoded form' => '%2e%2e%2fsecret.js',
        'a traversal segment'    => '../secret.js',
    ];
    foreach ($unsafeKeys as $label => $unsafePath) {
        $fixture = build_lock(['files' => [
            'assets/studio.js'  => 'sha384-' . base64_encode(hash('sha384', 'script bytes', true)),
            'assets/studio.css' => 'sha384-' . base64_encode(hash('sha384', 'style bytes', true)),
            $unsafePath         => 'sha384-' . base64_encode(hash('sha384', 'whatever', true)),
        ]]);
        check_release_lock($fixture['lock']->verifiedRelease() === null, "{$label} ('{$unsafePath}') anywhere in the digest inventory is rejected outright");
    }
}

// ── 7) Network/transport failure fails closed, never throws ───────────────
echo "\n7) a network error or non-200 response fails closed\n";
{
    $serverError = build_lock(httpStatus: 503);
    check_release_lock($serverError['lock']->verifiedRelease() === null, 'a non-200 upstream response is rejected');

    $networkError = build_lock(httpStatus: null); // transport's own stand-in for is_wp_error()
    check_release_lock($networkError['lock']->verifiedRelease() === null, 'a transport-level network error (no status) is rejected');
}

// ── 8) The manifest URL itself must be exactly the pinned one — a lock can
//      never be pointed at an arbitrary URL by anything other than its own
//      constructor pin ───────────────────────────────────────────────────
echo "\n8) only the pin's own https manifest URL is ever requested\n";
{
    $fixture = build_lock();
    check_release_lock(
        str_starts_with($fixture['pin']['manifestUrl'], 'https://'),
        'the fixture pin itself is https, matching what isApprovedManifestUrl() requires',
    );
    // A non-https pin is rejected before any transport call.
    $insecure = new StudioReleaseLock([
        'releaseId'              => 'fixture-release-1',
        'manifestUrl'            => 'http://wex-fixture.test/releases/fixture-release-1/manifest.json',
        'expectedManifestDigest' => $fixture['pin']['expectedManifestDigest'],
        'expectedContract'       => 'wex-studio-host@1',
    ], static function (string $url): array {
        throw new \RuntimeException('transport must never be called for a non-https manifest URL');
    });
    check_release_lock($insecure->verifiedRelease() === null, 'a non-https pinned manifest URL is rejected before any request is made');
}

// ── 9) Caching: a second call against the same pin does not re-fetch ──────
echo "\n9) a verified release is cached, so a second call does not re-hit the transport\n";
{
    $manifest = [
        'contract' => 'wex-studio-host@1', 'entry' => 'assets/studio.js', 'styles' => ['assets/studio.css'],
        'version' => 'cache-release', 'releaseId' => 'cache-release', 'sourceSha' => 'deadbeef',
        'files' => ['assets/studio.js' => 'sha384-' . base64_encode(hash('sha384', 'script bytes', true)), 'assets/studio.css' => 'sha384-' . base64_encode(hash('sha384', 'style bytes', true))],
    ];
    $body = (string) json_encode($manifest);
    $pin = [
        'releaseId' => 'cache-release',
        'manifestUrl' => 'https://wex-fixture.test/releases/cache-release/manifest.json',
        'expectedManifestDigest' => 'sha384-' . base64_encode(hash('sha384', $body, true)),
        'expectedContract' => 'wex-studio-host@1',
    ];
    $calls = 0;
    $lock = new StudioReleaseLock($pin, function (string $url) use (&$calls, $body): array {
        $calls++;
        return ['status' => 200, 'body' => $body];
    });

    $first  = $lock->verifiedRelease();
    $second = $lock->verifiedRelease();
    check_release_lock($first !== null && $second === $first, 'two calls against the same pin return the same verified release');
    check_release_lock($calls === 1, 'the transport is invoked only once; the second call is served from cache', $calls);
}

// ── 10) Switching the active pin — forward to a new release, or back to a
//       previously retained one — works purely by constructing the lock
//       with a different pin. No asset is copied anywhere for this test ──
echo "\n10) switching the pin (release promotion or rollback) needs no asset copy, just a different pin\n";
{
    $releaseA = build_lock(['releaseId' => 'release-a', 'version' => 'release-a'], ['releaseId' => 'release-a', 'manifestUrl' => 'https://wex-fixture.test/releases/release-a/manifest.json']);
    $releaseB = build_lock(['releaseId' => 'release-b', 'version' => 'release-b'], ['releaseId' => 'release-b', 'manifestUrl' => 'https://wex-fixture.test/releases/release-b/manifest.json']);

    $verifiedA = $releaseA['lock']->verifiedRelease();
    $verifiedB = $releaseB['lock']->verifiedRelease();
    check_release_lock($verifiedA !== null && $verifiedA['releaseId'] === 'release-a', 'release A verifies under its own pin');
    check_release_lock($verifiedB !== null && $verifiedB['releaseId'] === 'release-b', 'release B verifies under its own, different pin — proving the mechanism is pin-driven, not hardcoded to one release');

    // "Rollback": re-pin a release id that was already retained/verified before, same as release A above.
    $rollback = build_lock(['releaseId' => 'release-a', 'version' => 'release-a'], ['releaseId' => 'release-a', 'manifestUrl' => 'https://wex-fixture.test/releases/release-a/manifest.json']);
    $verifiedRollback = $rollback['lock']->verifiedRelease();
    check_release_lock($verifiedRollback !== null && $verifiedRollback['releaseId'] === 'release-a', 'rolling back to a previously retained release id verifies identically, with no asset copy involved');
}

// ── 11) Constructor wiring: with no explicit pin, the lock resolves its pin
//       through StudioStagingPointer; an explicit pin still bypasses it ───
echo "\n11) with no explicit pin, verifiedRelease() resolves through StudioStagingPointer end to end\n";
{
    $manifest = [
        'contract' => 'wex-studio-host@1', 'entry' => 'assets/studio.js', 'styles' => [],
        'releaseId' => 'wex-studio-v1-cccccccccccccccccccccccccccccccccccccccc',
        'files' => ['assets/studio.js' => 'sha384-' . base64_encode(hash('sha384', 'script bytes', true))],
    ];
    $manifestBody = (string) json_encode($manifest);
    $pointerBody = (string) json_encode([
        'schema' => 'wex-studio-staging-pointer@1', 'contract' => 'wex-studio-host@1',
        'sequence' => 1, 'change' => 'advance',
        'releaseId' => 'wex-studio-v1-cccccccccccccccccccccccccccccccccccccccc',
        'manifestPath' => 'wex-studio-v1-cccccccccccccccccccccccccccccccccccccccc/manifest.json',
        'manifestDigest' => 'sha384-' . base64_encode(hash('sha384', $manifestBody, true)),
        'previous' => null,
    ]);

    $GLOBALS['__options'] = [];
    // The pointer and the manifest are two distinct URLs; one stub transport
    // routes each to its own fixture body, reused for both the pointer
    // collaborator's own fetch and StudioReleaseLock's subsequent manifest fetch.
    $routedTransport = static function (string $url) use ($pointerBody, $manifestBody): array {
        return str_contains($url, 'staging-pointer.json')
            ? ['status' => 200, 'body' => $pointerBody]
            : ['status' => 200, 'body' => $manifestBody];
    };
    $pointer = new StudioStagingPointer($routedTransport, static fn(): string => 'staging');

    $lockWithPointer = new StudioReleaseLock(null, $routedTransport, $pointer);
    $release = $lockWithPointer->verifiedRelease();
    check_release_lock(
        ($release['releaseId'] ?? null) === 'wex-studio-v1-cccccccccccccccccccccccccccccccccccccccc',
        'with no explicit pin, the constructor resolves through StudioStagingPointer and still verifies the resulting manifest in full',
    );

    // An explicit pin always bypasses the pointer entirely — proven by a
    // pointer collaborator whose transport would throw if ever consulted.
    $neverCalled = new StudioStagingPointer(
        static function (string $url): array { throw new \RuntimeException('pointer must not be consulted when an explicit pin is given'); },
        static fn(): string => 'staging',
    );
    $explicitPinFixture = build_lock();
    $lockWithExplicitPin = new StudioReleaseLock(
        $explicitPinFixture['pin'],
        static fn(string $url): array => ['status' => 200, 'body' => $explicitPinFixture['body']],
        $neverCalled,
    );
    check_release_lock(
        $lockWithExplicitPin->verifiedRelease() !== null,
        'an explicit pin verifies normally even when a pointer collaborator is also supplied',
    );
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio release lock checks passed.\n";
