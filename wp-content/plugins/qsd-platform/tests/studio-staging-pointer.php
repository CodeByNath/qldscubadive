<?php

declare(strict_types=1);

// StudioStagingPointer: the one allowlisted fetch that lets StudioReleaseLock
// follow WEX's published staging pointer, on staging only, with no QSD code
// commit — while a durable (get_option) record of the highest accepted
// pointer sequence makes a replayed or downgraded pointer provably unable to
// move the active release backwards, including across a simulated process
// restart (a fresh instance sharing only the durable option store).

$__options = [];
function get_option(string $key, mixed $default = false): mixed
{
    global $__options;
    return array_key_exists($key, $__options) ? $__options[$key] : $default;
}
function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool
{
    global $__options;
    $__options[$key] = $value;
    return true;
}

require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetPath.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioStagingPointer.php';

use QSD\Platform\Modules\StudioHost\StudioStagingPointer;

$failures = [];
function check_pointer(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

const STATIC_FALLBACK = [
    'releaseId'              => 'wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d',
    'manifestUrl'            => 'https://codebynath.github.io/WEXdesigns/studio-releases/wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d/manifest.json',
    'expectedManifestDigest' => 'sha384-TGJL9R8YXT5xSlCTXoBG8NtbtuBnbXUKEZiqzlU5MBnGuoIgoREBquPzbO7Cob7W',
    'expectedContract'       => 'wex-studio-host@1',
];

function pointer_body(array $overrides = []): string
{
    $pointer = array_merge([
        'schema'         => 'wex-studio-staging-pointer@1',
        'contract'       => 'wex-studio-host@1',
        'sequence'       => 1,
        'change'         => 'advance',
        'releaseId'      => 'wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'manifestPath'   => 'wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/manifest.json',
        'manifestDigest' => 'sha384-' . base64_encode(hash('sha384', 'manifest-1', true)),
        'previous'       => null,
    ], $overrides);
    return (string) json_encode($pointer);
}

/** @return StudioStagingPointer */
function make_pointer(?string $httpStatus200Body = null, ?int $status = 200, string $env = 'staging'): StudioStagingPointer
{
    $transport = function (string $url) use ($httpStatus200Body, $status): array {
        return ['status' => $status, 'body' => $status === 200 ? $httpStatus200Body : null];
    };
    return new StudioStagingPointer($transport, static fn(): string => $env);
}

// Pushes the durable record's checkedAt back past the 300s recheck window,
// simulating a later real request where a revalidating fetch is actually due.
function force_recheck_due(): void
{
    global $__options;
    $state = $__options['qsd_studio_pointer_state'] ?? null;
    if (is_array($state)) {
        $state['checkedAt'] = time() - 301;
        $__options['qsd_studio_pointer_state'] = $state;
    }
}

// ── 1) Wrong environment: the pointer is never even consulted ────────────
echo "1) outside the staging environment the static fallback is returned untouched, no fetch made\n";
{
    $pointer = new StudioStagingPointer(
        static function (string $url): array { throw new \RuntimeException('transport must never be called outside staging'); },
        static fn(): string => 'production',
    );
    check_pointer($pointer->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'production environment short-circuits to the static fallback pin');
}

// ── 2) First-run bootstrap: no durable state yet, a valid pointer adopts ──
echo "\n2) a valid pointer on first run (no durable state) is adopted\n";
{
    $GLOBALS['__options'] = [];
    $pointer = make_pointer(pointer_body());
    $pin = $pointer->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === 'wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'first-run pointer release id is adopted');
    check_pointer($pin['manifestUrl'] === 'https://codebynath.github.io/WEXdesigns/studio-releases/wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/manifest.json', 'manifestUrl is derived from releaseId + manifestPath, not supplied directly');
    check_pointer($pin['expectedManifestDigest'] === 'sha384-' . base64_encode(hash('sha384', 'manifest-1', true)), 'manifestDigest is carried through from the pointer');
}

// ── 3) Successful approved advance — same code, new transport response,
//      simulating two separate requests sharing only the durable option ───
echo "\n3) an approved advance to a higher sequence adopts the new release with no code change\n";
{
    $GLOBALS['__options'] = [];
    $first = make_pointer(pointer_body(['sequence' => 1, 'releaseId' => 'wex-studio-v1-1111111111111111111111111111111111111111', 'manifestPath' => 'wex-studio-v1-1111111111111111111111111111111111111111/manifest.json']));
    $pinFirst = $first->resolvePin(STATIC_FALLBACK);
    check_pointer($pinFirst['releaseId'] === 'wex-studio-v1-1111111111111111111111111111111111111111', 'first request adopts sequence 1');

    // Past the bounded recheck window, as a later real request would be.
    force_recheck_due();

    // A brand-new instance (simulating a new HTTP request/process) sees the
    // durably stored sequence 1 and advances to the newly published sequence 2.
    $second = make_pointer(pointer_body(['sequence' => 2, 'releaseId' => 'wex-studio-v1-2222222222222222222222222222222222222222', 'manifestPath' => 'wex-studio-v1-2222222222222222222222222222222222222222/manifest.json', 'previous' => 'wex-studio-v1-1111111111111111111111111111111111111111']));
    $pinSecond = $second->resolvePin(STATIC_FALLBACK);
    check_pointer($pinSecond['releaseId'] === 'wex-studio-v1-2222222222222222222222222222222222222222', 'a fresh instance advances to the higher-sequence release purely from durable state + a new pointer response');
}

// ── 4) Rollback: a higher sequence may legitimately re-name an earlier
//      release id — only the sequence must move forward ──────────────────
echo "\n4) rollback (higher sequence, earlier release id) is accepted\n";
{
    $GLOBALS['__options'] = [];
    make_pointer(pointer_body(['sequence' => 5, 'releaseId' => 'wex-studio-v1-5555555555555555555555555555555555555555', 'manifestPath' => 'wex-studio-v1-5555555555555555555555555555555555555555/manifest.json']))->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $rollback = make_pointer(pointer_body(['sequence' => 6, 'change' => 'rollback', 'releaseId' => 'wex-studio-v1-1111111111111111111111111111111111111111', 'manifestPath' => 'wex-studio-v1-1111111111111111111111111111111111111111/manifest.json', 'previous' => 'wex-studio-v1-5555555555555555555555555555555555555555']));
    $pin = $rollback->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === 'wex-studio-v1-1111111111111111111111111111111111111111', 'a forward-moving sequence may roll the named release back to a previously retained one');
}

// ── 5) Cache/restart: within the recheck window, a fresh instance serves
//      the durable record without calling the transport again ────────────
echo "\n5) within the recheck window, a fresh instance never re-fetches the pointer\n";
{
    $GLOBALS['__options'] = [];
    make_pointer(pointer_body())->resolvePin(STATIC_FALLBACK);

    $calls = 0;
    $noFetch = new StudioStagingPointer(
        function (string $url) use (&$calls): array { $calls++; return ['status' => 200, 'body' => pointer_body(['sequence' => 99])]; },
        static fn(): string => 'staging',
    );
    $pin = $noFetch->resolvePin(STATIC_FALLBACK);
    check_pointer($calls === 0, 'the transport is not called again inside the recheck window', $calls);
    check_pointer($pin['releaseId'] === 'wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'the durable release from the earlier request is still served');
}

// ── 6) Invalid schema/contract/keys/shape all fail closed to the durable
//      or static fallback, never partially trusted ────────────────────────
echo "\n6) schema, contract, key-set, and field-shape violations are all rejected\n";
{
    $cases = [
        'wrong schema'               => pointer_body(['schema' => 'wex-studio-staging-pointer@2']),
        'wrong contract'              => pointer_body(['contract' => 'wex-studio-host@2']),
        'extra unexpected key'        => (function () { $p = json_decode(pointer_body(), true); $p['extra'] = 'x'; return (string) json_encode($p); })(),
        'missing required key'       => (function () { $p = json_decode(pointer_body(), true); unset($p['previous']); return (string) json_encode($p); })(),
        'non-integer sequence'        => pointer_body(['sequence' => '1']),
        'zero sequence'                => pointer_body(['sequence' => 0]),
        'disallowed change value'    => pointer_body(['change' => 'wipe']),
        'malformed releaseId'         => pointer_body(['releaseId' => 'not-a-real-release-id']),
        'manifestPath not derived'   => pointer_body(['manifestPath' => 'some-other-path/manifest.json']),
        'manifestPath traversal'      => pointer_body(['manifestPath' => '../secret/manifest.json']),
        'malformed manifestDigest'    => pointer_body(['manifestDigest' => 'not-a-digest']),
        'malformed previous'          => pointer_body(['previous' => 'not-a-release-id']),
        'not JSON at all'              => 'not json',
        'JSON but not an object'      => '[1,2,3]',
    ];
    foreach ($cases as $label => $body) {
        $GLOBALS['__options'] = [];
        $pin = make_pointer($body)->resolvePin(STATIC_FALLBACK);
        check_pointer($pin === STATIC_FALLBACK, "{$label} is rejected; static fallback is served with no durable state yet");
    }
}

// ── 7) Replay/downgrade: an equal or lower sequence never moves the active
//      release, even when it names a different release id ────────────────
echo "\n7) a replayed or downgraded sequence is ignored, keeping the retained release\n";
{
    $GLOBALS['__options'] = [];
    make_pointer(pointer_body(['sequence' => 10, 'releaseId' => 'wex-studio-v1-1010101010101010101010101010101010101010', 'manifestPath' => 'wex-studio-v1-1010101010101010101010101010101010101010/manifest.json']))->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $equal = make_pointer(pointer_body(['sequence' => 10, 'releaseId' => 'wex-studio-v1-9999999999999999999999999999999999999999', 'manifestPath' => 'wex-studio-v1-9999999999999999999999999999999999999999/manifest.json']));
    $pinEqual = $equal->resolvePin(STATIC_FALLBACK);
    check_pointer($pinEqual['releaseId'] === 'wex-studio-v1-1010101010101010101010101010101010101010', 'an equal (replayed) sequence with a different release id is ignored');
    force_recheck_due();

    $lower = make_pointer(pointer_body(['sequence' => 3, 'releaseId' => 'wex-studio-v1-0303030303030303030303030303030303030303', 'manifestPath' => 'wex-studio-v1-0303030303030303030303030303030303030303/manifest.json']));
    $pinLower = $lower->resolvePin(STATIC_FALLBACK);
    check_pointer($pinLower['releaseId'] === 'wex-studio-v1-1010101010101010101010101010101010101010', 'a lower (downgrade) sequence is rejected, keeping the higher one already retained');
}

// ── 8) Redirect / non-200 response and a transport-level network error both
//      fail closed exactly like StudioReleaseLock's own manifest fetch ────
echo "\n8) a redirect response, a non-200 response, and a network error all fail closed\n";
{
    $GLOBALS['__options'] = [];
    $redirect = make_pointer(null, 301); // redirection is disabled; GH Pages redirecting shows up as a non-200
    check_pointer($redirect->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'a redirect (non-200, not followed) is rejected; static fallback served with no durable state');

    $networkError = make_pointer(null, null);
    check_pointer($networkError->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'a transport-level network error is rejected; static fallback served with no durable state');
}

// ── 9) Outage after a prior successful adoption serves the last known good
//      release — never the original static constant, and never nothing ───
echo "\n9) an outage after a successful adoption serves the last independently verified release, not the original static constant\n";
{
    $GLOBALS['__options'] = [];
    make_pointer(pointer_body(['sequence' => 1, 'releaseId' => 'wex-studio-v1-1111111111111111111111111111111111111111', 'manifestPath' => 'wex-studio-v1-1111111111111111111111111111111111111111/manifest.json']))->resolvePin(STATIC_FALLBACK);

    force_recheck_due(); // so the next call actually attempts to re-fetch

    $outage = make_pointer(null, null); // network error on the follow-up fetch
    $pin = $outage->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === 'wex-studio-v1-1111111111111111111111111111111111111111', 'an outage serves the durably retained release, not the bootstrap static constant');
    check_pointer($pin !== STATIC_FALLBACK, 'the served pin is the retained release, distinct from the static fallback');
}

// ── 10) A corrupted/unreadable durable record never lets a pointer reset
//       the anti-replay baseline; it fails closed to the static pin ──────
echo "\n10) a corrupted durable record fails closed to the static pin and is left untouched\n";
{
    $GLOBALS['__options'] = ['qsd_studio_pointer_state' => ['not' => 'a valid record']];
    $pointer = make_pointer(pointer_body(['sequence' => 1]));
    $pin = $pointer->resolvePin(STATIC_FALLBACK);
    check_pointer($pin === STATIC_FALLBACK, 'a corrupted durable record serves the static fallback rather than trusting a fresh pointer as a new baseline');
    check_pointer($GLOBALS['__options']['qsd_studio_pointer_state'] === ['not' => 'a valid record'], 'the corrupted record itself is left untouched for investigation');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio staging pointer checks passed.\n";
