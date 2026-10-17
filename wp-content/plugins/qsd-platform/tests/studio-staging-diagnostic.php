<?php

declare(strict_types=1);

// StudioStagingDiagnostic: a single non-secret report of what the pointer
// and release lock actually resolve to, run for real on the deployed server
// over SSH (see app/cli/studio-staging-diagnostic.php and the
// "Studio staging activation diagnostic" CI step) — exercised here with
// injected transport/environment/marker seams for determinism.

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

$__transients = [];
function get_transient(string $key): mixed { global $__transients; return $__transients[$key] ?? false; }
function set_transient(string $key, mixed $value, int $ttl): bool { global $__transients; $__transients[$key] = $value; return true; }

require_once __DIR__ . '/../src/Modules/StudioHost/StudioAssetPath.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioManifestVerifier.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioStagingPointer.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioReleaseLock.php';
require_once __DIR__ . '/../src/Modules/StudioHost/StudioStagingDiagnostic.php';

use QSD\Platform\Modules\StudioHost\StudioStagingDiagnostic;
use QSD\Platform\Modules\StudioHost\StudioReleaseLock;

$failures = [];
function check_diagnostic(bool $condition, string $label, mixed $detail = null): void
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

function good_manifest(string $releaseId): array
{
    $fileDigest = 'sha384-' . base64_encode(hash('sha384', 'asset-bytes-' . $releaseId, true));
    $manifest = [
        'contract'  => 'wex-studio-host@1',
        'releaseId' => $releaseId,
        'entry'     => 'app.js',
        'styles'    => ['app.css'],
        'files'     => ['app.js' => $fileDigest, 'app.css' => $fileDigest],
    ];
    $body = (string) json_encode($manifest);
    return ['body' => $body, 'digest' => 'sha384-' . base64_encode(hash('sha384', $body, true))];
}

function good_marker(): array
{
    return ['deployedBy' => 'qsd-platform-ci', 'branch' => 'staging', 'sha' => str_repeat('a', 40), 'deployedAt' => '2026-10-17T00:00:00Z'];
}

$staticPin = StudioReleaseLock::staticFallback();
$transportFor = function (array $byUrl): \Closure {
    return function (string $url) use ($byUrl): array {
        return $byUrl[$url] ?? ['status' => null, 'body' => null];
    };
};

// ── 1) Gate inactive: no staging environment, no deployment marker ───────
// Note: the real `StudioReleaseLock::staticFallback()` digest is a fixed
// constant tied to WEX's actual published manifest body, which these tests
// have no way to reproduce — so these fixtures leave that one manifest
// fetch unregistered (transport outage) rather than fabricate a body that
// could never hash to the real digest; `manifestVerified` is correctly
// `false` in both cases below, exactly as a genuine outage would report.
echo "1) an inactive gate reports environmentType/deploymentProven/active accurately and serves the static pin\n";
{
    $GLOBALS['__options'] = [];
    $GLOBALS['__transients'] = [];
    $report = StudioStagingDiagnostic::report($transportFor([]), static fn(): string => 'production', static fn(): ?array => null);

    check_diagnostic($report['environmentType'] === 'production', 'environmentType reported as production');
    check_diagnostic($report['deploymentProven'] === false, 'deploymentProven is false with no marker');
    check_diagnostic($report['gateActive'] === false, 'gateActive is false');
    check_diagnostic($report['storedSequence'] === null, 'storedSequence is null (pointer never consulted)');
    check_diagnostic($report['pinSource'] === 'static-pin', 'pinSource is static-pin');
    check_diagnostic($report['releaseId'] === $staticPin['releaseId'], 'releaseId is the fixed pin', $report);
    check_diagnostic($report['fallbackReason'] === 'environment gate inactive (wp_get_environment_type() is not staging, and no deployment-proof marker is present)', 'fallbackReason names the inactive gate ahead of manifest status', $report['fallbackReason']);
}

// ── 2) Gate active via wp_get_environment_type() alone, and the selected
//      manifest cannot be verified: the specific verification-failure
//      reason takes priority over "static pin, nothing newer" ───────────
echo "\n2) an active gate whose selected manifest cannot be verified reports that specific reason\n";
{
    $GLOBALS['__options'] = [];
    $GLOBALS['__transients'] = [];
    $report = StudioStagingDiagnostic::report($transportFor([]), static fn(): string => 'staging', static fn(): ?array => null);

    check_diagnostic($report['environmentType'] === 'staging', 'environmentType reported as staging');
    check_diagnostic($report['gateActive'] === true, 'gateActive is true via environment alone');
    check_diagnostic($report['pinSource'] === 'static-pin', 'pinSource is static-pin (pointer unreachable, first run)');
    check_diagnostic($report['manifestVerified'] === false, 'manifestVerified is false (no manifest fixture registered)');
    check_diagnostic($report['fallbackReason'] === 'selected manifest could not be verified (outage or tamper)', 'fallbackReason names the verification failure, ahead of the static-pin reason', $report['fallbackReason']);
}

// ── 3) Gate active via the deployment-proof marker alone (environment type
//      unset/production), and a real pointer-driven release is adopted ───
echo "\n3) the deployment-proof marker alone activates the gate and a verified pointer-driven release is reported\n";
{
    $GLOBALS['__options'] = [];
    $GLOBALS['__transients'] = [];
    $newRelease = 'wex-studio-v1-bace0c02f8eea0accc0a34abe50b9b3f9495f04a';
    $good = good_manifest($newRelease);
    $pointerBody = [
        'schema' => 'wex-studio-staging-pointer@1', 'contract' => 'wex-studio-host@1', 'sequence' => 2, 'change' => 'advance',
        'releaseId' => $newRelease, 'manifestPath' => $newRelease . '/manifest.json', 'manifestDigest' => $good['digest'],
        'previous' => ['sequence' => 1, 'releaseId' => $staticPin['releaseId'], 'manifestDigest' => good_manifest($staticPin['releaseId'])['digest']],
    ];
    // Seed durable state at sequence 1 so the sequence-2 pointer chains from something real.
    $GLOBALS['__options']['qsd_studio_pointer_state'] = [
        'sequence'       => 1,
        'releaseId'      => $staticPin['releaseId'],
        'manifestUrl'    => $staticPin['manifestUrl'],
        'manifestDigest' => good_manifest($staticPin['releaseId'])['digest'],
        'contract'       => 'wex-studio-host@1',
        'checkedAt'      => time() - 301,
    ];
    $byUrl = [
        RELEASES_BASE . $newRelease . '/manifest.json' => ['status' => 200, 'body' => $good['body']],
        POINTER_URL                                     => ['status' => 200, 'body' => (string) json_encode($pointerBody)],
    ];
    $report = StudioStagingDiagnostic::report($transportFor($byUrl), static fn(): string => 'production', static fn(): ?array => good_marker());

    check_diagnostic($report['environmentType'] === 'production', 'environmentType still reported as production');
    check_diagnostic($report['deploymentProven'] === true, 'deploymentProven is true');
    check_diagnostic($report['gateActive'] === true, 'gateActive is true via the marker alone');
    check_diagnostic($report['storedSequence'] === 2, 'storedSequence advances to 2', $report);
    check_diagnostic($report['pinSource'] === 'pointer', 'pinSource is pointer');
    check_diagnostic($report['releaseId'] === $newRelease, 'releaseId is the pointer-driven release', $report);
    check_diagnostic($report['manifestVerified'] === true, 'the adopted release\'s manifest verifies');
    check_diagnostic($report['fallbackReason'] === 'pointer-driven release active (approved advance adopted with no QSD code commit)', 'fallbackReason names the active pointer-driven release', $report['fallbackReason']);
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio staging diagnostic checks passed.\n";
