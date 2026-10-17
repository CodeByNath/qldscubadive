<?php

declare(strict_types=1);

// StudioStagingPointer: the one allowlisted fetch that lets StudioReleaseLock
// follow WEX's published staging pointer, on staging only, with no QSD code
// commit. A candidate above the stored sequence is adopted only when ALL
// THREE hold: its own shape is valid (per WEX ADR 0025, including the
// `previous` object required after sequence 1), its `previous` exactly
// names the currently stored sequence/releaseId/manifestDigest (an unbroken
// approval chain — a gap this instance cannot independently verify from a
// single current-state fetch fails closed instead of being silently
// bridged), and its named manifest independently verifies in full (via the
// shared StudioManifestVerifier). A durable (get_option) record of the
// highest accepted sequence, itself re-validated on every read, makes a
// replayed, downgraded, unchained, or tampered pointer provably unable to
// move the active release, including across a simulated process restart
// (a fresh instance sharing only the durable option store).

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
require_once __DIR__ . '/../src/Modules/StudioHost/StudioManifestVerifier.php';
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

const RELEASES_BASE = 'https://codebynath.github.io/WEXdesigns/studio-releases/';
const POINTER_URL = 'https://codebynath.github.io/WEXdesigns/studio-releases/staging-pointer.json';

const STATIC_FALLBACK = [
    'releaseId'              => 'wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d',
    'manifestUrl'            => 'https://codebynath.github.io/WEXdesigns/studio-releases/wex-studio-v1-7d1dd544fb92f5d019e5034cd15ede94857b671d/manifest.json',
    'expectedManifestDigest' => 'sha384-TGJL9R8YXT5xSlCTXoBG8NtbtuBnbXUKEZiqzlU5MBnGuoIgoREBquPzbO7Cob7W',
    'expectedContract'       => 'wex-studio-host@1',
];

/** A manifest body that verifies cleanly for the given release id, plus its own digest. */
function good_manifest(string $releaseId): array
{
    $fileDigest = 'sha384-' . base64_encode(hash('sha384', 'asset-bytes-' . $releaseId, true));
    $manifest = [
        'contract'  => 'wex-studio-host@1',
        'releaseId' => $releaseId,
        'entry'     => 'app.js',
        'styles'    => ['app.css'],
        'files'     => [
            'app.js'  => $fileDigest,
            'app.css' => $fileDigest,
        ],
    ];
    $body = (string) json_encode($manifest);
    return ['body' => $body, 'digest' => 'sha384-' . base64_encode(hash('sha384', $body, true))];
}

/** The exact `previous` object naming `$releaseId` at `$sequence`, chained from its own real manifest digest. */
function previous_for(int $sequence, string $releaseId): array
{
    return ['sequence' => $sequence, 'releaseId' => $releaseId, 'manifestDigest' => good_manifest($releaseId)['digest']];
}

/**
 * A pointer JSON body naming `$releaseId`, whose `manifestDigest` matches a
 * real, independently verifiable manifest fixture registered into `$byUrl`
 * (the URL-routed transport map) — unless `$manifestOutcome` says the
 * manifest itself should fail verification (outage, tamper), to exercise
 * the "shape+chain-valid pointer, unverifiable manifest" path distinctly
 * from a shape- or chain-invalid pointer.
 */
function register_candidate(array &$byUrl, string $releaseId, int $sequence, array $overrides = [], ?array $previous = null, string $manifestOutcome = 'good'): string
{
    $manifestUrl = RELEASES_BASE . $releaseId . '/manifest.json';
    $good = good_manifest($releaseId);

    if ($manifestOutcome === 'good') {
        $byUrl[$manifestUrl] = ['status' => 200, 'body' => $good['body']];
        $manifestDigest = $good['digest'];
    } elseif ($manifestOutcome === 'outage') {
        $byUrl[$manifestUrl] = ['status' => null, 'body' => null];
        $manifestDigest = $good['digest'];
    } else { // 'tampered': the pointer's declared digest does not match any real manifest body
        $byUrl[$manifestUrl] = ['status' => 200, 'body' => $good['body']];
        $manifestDigest = 'sha384-' . base64_encode(hash('sha384', 'not-the-real-body', true));
    }

    $pointer = array_merge([
        'schema'         => 'wex-studio-staging-pointer@1',
        'contract'       => 'wex-studio-host@1',
        'sequence'       => $sequence,
        'change'         => 'advance',
        'releaseId'      => $releaseId,
        'manifestPath'   => $releaseId . '/manifest.json',
        'manifestDigest' => $manifestDigest,
        'previous'       => $previous,
    ], $overrides);

    $byUrl[POINTER_URL] = ['status' => 200, 'body' => (string) json_encode($pointer)];
    return $releaseId;
}

/** @return StudioStagingPointer */
function make_pointer_routed(array $byUrl, string $env = 'staging'): StudioStagingPointer
{
    $transport = function (string $url) use ($byUrl): array {
        return $byUrl[$url] ?? ['status' => null, 'body' => null];
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

const R1 = 'wex-studio-v1-1111111111111111111111111111111111111111';
const R2 = 'wex-studio-v1-2222222222222222222222222222222222222222';
const R5 = 'wex-studio-v1-5555555555555555555555555555555555555555';

// ── 1) Wrong environment: the pointer is never even consulted ────────────
echo "1) outside the staging environment the static fallback is returned untouched, no fetch made\n";
{
    $pointer = new StudioStagingPointer(
        static function (string $url): array { throw new \RuntimeException('transport must never be called outside staging'); },
        static fn(): string => 'production',
    );
    check_pointer($pointer->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'production environment short-circuits to the static fallback pin');
}

// ── 2) First-run bootstrap: a shape- and manifest-verified pointer adopts ─
echo "\n2) a valid, manifest-verified pointer on first run (no durable state) is adopted\n";
{
    $GLOBALS['__options'] = [];
    $byUrl = [];
    register_candidate($byUrl, R1, 1);
    $pin = make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === R1, 'first-run pointer release id is adopted');
    check_pointer($pin['manifestUrl'] === RELEASES_BASE . R1 . '/manifest.json', 'manifestUrl is derived from releaseId + manifestPath, not supplied directly');
    check_pointer($pin['expectedManifestDigest'] === good_manifest(R1)['digest'], 'manifestDigest is the real, independently verified manifest digest');
}

// ── 3) A real consecutive advance, correctly chained ──────────────────────
echo "\n3) a real sequence-2 advance, whose `previous` exactly chains from the stored release, adopts the new release with no code change\n";
{
    $GLOBALS['__options'] = [];
    $byUrl1 = [];
    register_candidate($byUrl1, R1, 1);
    $pinFirst = make_pointer_routed($byUrl1)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinFirst['releaseId'] === R1, 'first request adopts sequence 1');

    force_recheck_due();

    $byUrl2 = [];
    register_candidate($byUrl2, R2, 2, previous: previous_for(1, R1));
    $pinSecond = make_pointer_routed($byUrl2)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinSecond['releaseId'] === R2, 'a fresh instance advances to the higher-sequence release purely from durable state + a new, chain- and manifest-verified pointer response');
}

// ── 4) A legitimate skipped sequence whose `previous` still exactly
//      chains from the stored release is accepted — the arithmetic gap
//      alone proves nothing either way; only the chain linkage matters ───
echo "\n4) a skipped (non-consecutive) sequence whose `previous` still exactly names the stored release is accepted\n";
{
    $GLOBALS['__options'] = [];
    $byUrl1 = [];
    register_candidate($byUrl1, R1, 1);
    make_pointer_routed($byUrl1)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $byUrl2 = [];
    register_candidate($byUrl2, R2, 5, previous: previous_for(1, R1)); // sequence jumps 1 -> 5
    $pin = make_pointer_routed($byUrl2)->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === R2, 'a skipped sequence with a `previous` that exactly chains from the stored release is adopted');
}

// ── 5) A higher sequence whose `previous` does NOT name the stored
//      release — forged, stale, or from an unrelated lineage — is never
//      adopted, even though its own manifest would independently verify ──
echo "\n5) a higher sequence whose `previous` does not chain from the stored release fails closed, even with a manifest that would otherwise verify\n";
{
    $GLOBALS['__options'] = [];
    $byUrl1 = [];
    register_candidate($byUrl1, R1, 1);
    make_pointer_routed($byUrl1)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $unrelated = 'wex-studio-v1-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $byUrl2 = [];
    // sequence 5 > stored sequence 1, and R2's own manifest is perfectly
    // valid — but `previous` cites an unrelated release, not R1/sequence 1,
    // so no provable chain exists from what is actually stored.
    register_candidate($byUrl2, R2, 5, previous: previous_for(3, $unrelated));
    $pin = make_pointer_routed($byUrl2)->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === R1, 'an unchained higher-sequence candidate is rejected; the retained release is kept');
}

// ── 6) Rollback: a higher sequence may legitimately re-name an earlier
//      release id, proven by the same exact-chain requirement ───────────
echo "\n6) rollback (higher sequence, earlier release id, correctly chained `previous`) is accepted\n";
{
    $GLOBALS['__options'] = [];
    $r4 = 'wex-studio-v1-4444444444444444444444444444444444444444';
    $byUrl1 = [];
    register_candidate($byUrl1, R5, 5, previous: previous_for(4, $r4));
    make_pointer_routed($byUrl1)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $byUrl2 = [];
    register_candidate($byUrl2, R1, 6, ['change' => 'rollback'], previous: previous_for(5, R5));
    $pin = make_pointer_routed($byUrl2)->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === R1, 'a forward-moving sequence, correctly chained, may roll the named release back to a previously retained one');
}

// ── 7) Cache/restart: within the recheck window, a fresh instance serves
//      the durable record without calling the transport again ────────────
echo "\n7) within the recheck window, a fresh instance never re-fetches the pointer or any manifest\n";
{
    $GLOBALS['__options'] = [];
    $byUrl = [];
    register_candidate($byUrl, R1, 1);
    make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);

    $calls = 0;
    $noFetch = new StudioStagingPointer(
        function (string $url) use (&$calls): array { $calls++; return ['status' => 200, 'body' => '{}']; },
        static fn(): string => 'staging',
    );
    $pin = $noFetch->resolvePin(STATIC_FALLBACK);
    check_pointer($calls === 0, 'the transport is not called again inside the recheck window', $calls);
    check_pointer($pin['releaseId'] === R1, 'the durable release from the earlier request is still served');
}

// ── 8) Invalid schema/contract/keys/shape — including the ADR 0025
//      `previous` object — all fail closed, never partially trusted ───────
echo "\n8) schema, contract, key-set, and field-shape violations (including a forged/malformed `previous`) are all rejected\n";
{
    $validPreviousForSeq2 = previous_for(1, R1);

    $base = function (array $overrides) use ($validPreviousForSeq2): array {
        return array_merge([
            'schema'         => 'wex-studio-staging-pointer@1',
            'contract'       => 'wex-studio-host@1',
            'sequence'       => 2,
            'change'         => 'advance',
            'releaseId'      => R2,
            'manifestPath'   => R2 . '/manifest.json',
            'manifestDigest' => good_manifest(R2)['digest'],
            'previous'       => $validPreviousForSeq2,
        ], $overrides);
    };

    $cases = [
        'wrong schema'                           => $base(['schema' => 'wex-studio-staging-pointer@2']),
        'wrong contract'                          => $base(['contract' => 'wex-studio-host@2']),
        'extra unexpected key'                    => $base([]) + ['extra' => 'x'],
        'non-integer sequence'                    => $base(['sequence' => '2']),
        'zero sequence'                           => $base(['sequence' => 0]),
        'disallowed change value'                 => $base(['change' => 'wipe']),
        'malformed releaseId'                     => $base(['releaseId' => 'not-a-real-release-id']),
        'manifestPath not derived'                => $base(['manifestPath' => 'some-other-path/manifest.json']),
        'manifestPath traversal'                  => $base(['manifestPath' => '../secret/manifest.json']),
        'malformed manifestDigest'                 => $base(['manifestDigest' => 'not-a-digest']),
        'sequence 1 with a non-null previous'      => $base(['sequence' => 1, 'releaseId' => R1, 'manifestPath' => R1 . '/manifest.json', 'manifestDigest' => good_manifest(R1)['digest'], 'previous' => $validPreviousForSeq2]),
        'previous as a bare release-id string'     => $base(['previous' => R1]),
        'previous missing manifestDigest'          => $base(['previous' => ['sequence' => 1, 'releaseId' => R1]]),
        'previous with an extra key'               => $base(['previous' => $validPreviousForSeq2 + ['extra' => 'x']]),
        'previous.sequence non-integer'            => $base(['previous' => ['sequence' => '1', 'releaseId' => R1, 'manifestDigest' => good_manifest(R1)['digest']]]),
        'previous.releaseId malformed'             => $base(['previous' => ['sequence' => 1, 'releaseId' => 'bogus', 'manifestDigest' => good_manifest(R1)['digest']]]),
        'previous.manifestDigest malformed'        => $base(['previous' => ['sequence' => 1, 'releaseId' => R1, 'manifestDigest' => 'not-a-digest']]),
        'missing required key (previous)'          => (function () use ($base) { $p = $base([]); unset($p['previous']); return $p; })(),
        'not JSON at all'                           => null, // handled specially below
    ];

    foreach ($cases as $label => $pointerArray) {
        $GLOBALS['__options'] = [];
        $byUrl = [RELEASES_BASE . R2 . '/manifest.json' => ['status' => 200, 'body' => good_manifest(R2)['body']]];
        $byUrl[POINTER_URL] = $label === 'not JSON at all'
            ? ['status' => 200, 'body' => 'not json']
            : ['status' => 200, 'body' => (string) json_encode($pointerArray)];
        $pin = make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);
        check_pointer($pin === STATIC_FALLBACK, "{$label} is rejected; static fallback is served with no durable state yet");
    }

    // JSON but not an object — a separate shape failure from any of the above.
    $GLOBALS['__options'] = [];
    $byUrl = [POINTER_URL => ['status' => 200, 'body' => '[1,2,3]']];
    check_pointer(make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'JSON but not an object is rejected; static fallback is served with no durable state yet');
}

// ── 9) Unchanged-sequence identity and downgrade: an equal sequence must
//      name exactly the stored release (idempotent) — a different release
//      at the same sequence is an integrity violation, not an adoption;
//      a lower sequence is a plain downgrade. All three keep the retained
//      release. ───────────────────────────────────────────────────────────
echo "\n9) unchanged-sequence identity and downgrade: only the stored release is ever served\n";
{
    $GLOBALS['__options'] = [];
    $r9 = 'wex-studio-v1-0909090909090909090909090909090909090909';
    $r10 = 'wex-studio-v1-1010101010101010101010101010101010101010';
    $byUrl = [];
    register_candidate($byUrl, $r10, 10, previous: previous_for(9, $r9));
    make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    // 9a) Same sequence, same release/digest — idempotent re-serve.
    $byUrlSame = [];
    register_candidate($byUrlSame, $r10, 10, previous: previous_for(9, $r9));
    $pinSame = make_pointer_routed($byUrlSame)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinSame['releaseId'] === $r10, 'an unchanged sequence naming the same already-stored release is served as-is');
    force_recheck_due();

    // 9b) Same sequence, a DIFFERENT release — integrity violation.
    $r9999 = 'wex-studio-v1-9999999999999999999999999999999999999999';
    $byUrlEqual = [];
    register_candidate($byUrlEqual, $r9999, 10, previous: previous_for(9, $r10));
    $pinEqual = make_pointer_routed($byUrlEqual)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinEqual['releaseId'] === $r10, 'a different release claiming the same already-stored sequence number is rejected, keeping the original');
    force_recheck_due();

    // 9c) Lower sequence — plain downgrade.
    $r3 = 'wex-studio-v1-0303030303030303030303030303030303030303';
    $r2b = 'wex-studio-v1-0202020202020202020202020202020202020202';
    $byUrlLower = [];
    register_candidate($byUrlLower, $r3, 3, previous: previous_for(2, $r2b));
    $pinLower = make_pointer_routed($byUrlLower)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinLower['releaseId'] === $r10, 'a lower (downgrade) sequence is rejected, keeping the higher one already retained');
}

// ── 10) Redirect / non-200 response and a transport-level network error on
//       the pointer fetch itself both fail closed ─────────────────────────
echo "\n10) a redirect response, a non-200 response, and a network error on the pointer fetch all fail closed\n";
{
    $GLOBALS['__options'] = [];
    $redirect = make_pointer_routed([POINTER_URL => ['status' => 301, 'body' => null]]);
    check_pointer($redirect->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'a redirect (non-200, not followed) is rejected; static fallback served with no durable state');

    $networkError = make_pointer_routed([POINTER_URL => ['status' => null, 'body' => null]]);
    check_pointer($networkError->resolvePin(STATIC_FALLBACK) === STATIC_FALLBACK, 'a transport-level network error is rejected; static fallback served with no durable state');
}

// ── 11) Manifest outage/tamper AFTER a shape- and chain-valid pointer
//       advance never persists the candidate and never strands on it ─────
echo "\n11) a shape- and chain-valid pointer whose own manifest cannot be verified (outage or tamper) is never persisted\n";
{
    // 11a) True first run: the pointer shape is fine but its manifest is
    // unreachable — nothing durable exists yet, so the static fallback
    // (never an unverified candidate) is served.
    $GLOBALS['__options'] = [];
    $byUrlOutage = [];
    register_candidate($byUrlOutage, R1, 1, manifestOutcome: 'outage');
    $pinOutage = make_pointer_routed($byUrlOutage)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinOutage === STATIC_FALLBACK, 'first-run manifest outage after a shape-valid pointer serves the static fallback, never an unverified candidate');

    // 11b) Already-adopted release 1; a later, correctly-chained pointer
    // advances to release 2 but release 2's manifest is tampered (digest
    // does not match any real body) — the advance must be rejected and
    // release 1 retained, not stranded on the unverified candidate.
    $GLOBALS['__options'] = [];
    $byUrl1 = [];
    register_candidate($byUrl1, R1, 1);
    make_pointer_routed($byUrl1)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $byUrl2 = [];
    register_candidate($byUrl2, R2, 2, previous: previous_for(1, R1), manifestOutcome: 'tampered');
    $pinAfterTamper = make_pointer_routed($byUrl2)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinAfterTamper['releaseId'] === R1, 'a tampered-manifest advance is rejected; the last independently verified release is retained, not the unverified candidate');

    // 11c) Same, but the sequence-2 manifest is merely unreachable
    // (outage), not tampered — same outcome: retain release 1.
    $GLOBALS['__options'] = [];
    $byUrl1b = [];
    register_candidate($byUrl1b, R1, 1);
    make_pointer_routed($byUrl1b)->resolvePin(STATIC_FALLBACK);
    force_recheck_due();

    $byUrl2b = [];
    register_candidate($byUrl2b, R2, 2, previous: previous_for(1, R1), manifestOutcome: 'outage');
    $pinAfterOutage = make_pointer_routed($byUrl2b)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinAfterOutage['releaseId'] === R1, 'a manifest outage on an advance is rejected; the last independently verified release is retained');
}

// ── 12) Outage on the pointer fetch itself (not the manifest) after a
//       prior successful adoption still serves the last known good ───────
echo "\n12) an outage on the pointer fetch itself after a successful adoption serves the last independently verified release\n";
{
    $GLOBALS['__options'] = [];
    $byUrl = [];
    register_candidate($byUrl, R1, 1);
    make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);

    force_recheck_due();

    $outage = make_pointer_routed([POINTER_URL => ['status' => null, 'body' => null]]);
    $pin = $outage->resolvePin(STATIC_FALLBACK);
    check_pointer($pin['releaseId'] === R1, 'an outage on the pointer fetch serves the durably retained release, not the bootstrap static constant');
    check_pointer($pin !== STATIC_FALLBACK, 'the served pin is the retained release, distinct from the static fallback');
}

// ── 13) A corrupted/unreadable/tampered durable record never lets a
//       pointer reset the anti-replay baseline; it fails closed ──────────
echo "\n13) a corrupted or structurally-tampered durable record fails closed to the static pin and is left untouched\n";
{
    $GLOBALS['__options'] = ['qsd_studio_pointer_state' => ['not' => 'a valid record']];
    $byUrl = [];
    register_candidate($byUrl, R1, 1);
    $pin = make_pointer_routed($byUrl)->resolvePin(STATIC_FALLBACK);
    check_pointer($pin === STATIC_FALLBACK, 'a corrupted durable record serves the static fallback rather than trusting a fresh pointer as a new baseline');
    check_pointer($GLOBALS['__options']['qsd_studio_pointer_state'] === ['not' => 'a valid record'], 'the corrupted record itself is left untouched for investigation');

    // A record that is well-TYPED but carries a manifestUrl that was not
    // actually derived from its own releaseId — e.g. walked to a different
    // release's manifest — must also be treated as tampered, not trusted.
    $GLOBALS['__options'] = ['qsd_studio_pointer_state' => [
        'sequence'       => 5,
        'releaseId'      => R5,
        'manifestUrl'    => RELEASES_BASE . R1 . '/manifest.json', // mismatched on purpose
        'manifestDigest' => good_manifest(R5)['digest'],
        'contract'       => 'wex-studio-host@1',
        'checkedAt'      => time(),
    ]];
    $byUrlMismatch = [];
    register_candidate($byUrlMismatch, R1, 1);
    $pinMismatch = make_pointer_routed($byUrlMismatch)->resolvePin(STATIC_FALLBACK);
    check_pointer($pinMismatch === STATIC_FALLBACK, 'a durable record whose manifestUrl was not actually derived from its own releaseId is treated as tampered, not trusted');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio staging pointer checks passed.\n";
