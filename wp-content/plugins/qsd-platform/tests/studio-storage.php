<?php

declare(strict_types=1);

// StudioStorage: the host-private writable root for WEX's identity-space
// storage. Exercises the real class against a real temporary filesystem
// (only the ABSPATH constant and wp_json_encode() are stubbed) — isolation,
// fail-closed behaviour, idempotent initialization, and restart persistence
// are all genuine filesystem operations, not mocked. The root is required to
// resolve one directory ABOVE a fake "WordPress install" directory, exactly
// as production does, so a bug that put it back under the install root (or
// under an uploads-style path inside it) would show up as a real path
// assertion failure here, not just a reviewed intent.

$__testBase = sys_get_temp_dir() . '/qsd-studio-test-' . bin2hex(random_bytes(6));
$__wpRoot   = $__testBase . '/wordpress';
mkdir($__wpRoot, 0755, true);
define('ABSPATH', $__wpRoot . '/');

function wp_json_encode(mixed $value): string|false { return json_encode($value); }

require_once __DIR__ . '/../src/Modules/StudioHost/StudioStorage.php';

use QSD\Platform\Modules\StudioHost\StudioStorage;

$failures = [];
function check_studio_storage(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

// ── 1) Uninitialized by default; nothing is created merely by asking ──────
echo "1) a fresh root is uninitialized, and checking it creates nothing\n";
{
    global $__wpRoot, $__testBase;
    $storage = new StudioStorage();
    check_studio_storage(!$storage->isInitialized(), 'isInitialized() is false before any action');
    check_studio_storage(!is_dir($storage->root()), 'the root directory does not exist merely from checking status — no silent preapproval');
    check_studio_storage($storage->readIdentity() === null, 'readIdentity() on an uninitialized root returns null');

    check_studio_storage(
        !str_starts_with($storage->root(), $__wpRoot),
        'the storage root resolves OUTSIDE the WordPress install directory itself (ABSPATH), not merely outside its uploads folder',
        $storage->root(),
    );
    check_studio_storage(
        dirname($storage->root()) === $__testBase,
        'the root is exactly one directory above the WordPress install — one level outside any web server document root',
        [$storage->root(), $__testBase],
    );
}

// ── 2) Fail-closed before initialization ───────────────────────────────────
echo "\n2) writes are refused before explicit initialization\n";
{
    $storage = new StudioStorage();
    check_studio_storage($storage->writeIdentity('{"a":1}') === false, 'writeIdentity() on an uninitialized root returns false and writes nothing');
    check_studio_storage(!is_dir($storage->root()), 'the root still does not exist after a refused write');
}

// ── 3) Explicit initialization creates a protected, writable root ─────────
echo "\n3) initialize() is the one explicit administrator-approved action that provisions the root\n";
{
    $storage = new StudioStorage();
    $ok      = $storage->initialize(42);
    check_studio_storage($ok === true, 'initialize() succeeds against a writable uploads base');
    check_studio_storage($storage->isInitialized(), 'isInitialized() is now true');
    check_studio_storage(is_file($storage->root() . '/.htaccess'), 'a .htaccess denying all access is written');
    check_studio_storage(
        str_contains((string) file_get_contents($storage->root() . '/.htaccess'), 'Deny from all'),
        'the .htaccess actually denies web access (Apache 2.2 and 2.4 both covered)',
    );
    check_studio_storage(is_file($storage->root() . '/index.php'), 'an index.php guard is written');

    $marker = json_decode((string) file_get_contents($storage->root() . '/.initialized'), true);
    check_studio_storage($marker['initializedBy'] === 42, 'the marker records which user performed the initialization', $marker);
}

// ── 4) Idempotent: a second initialize() never re-stamps or resets ────────
echo "\n4) re-initializing is a no-op, never overwriting the original stamp\n";
{
    $storage = new StudioStorage();
    $storage->initialize(1);
    $firstMarker = file_get_contents($storage->root() . '/.initialized');

    $ok = $storage->initialize(999); // a different "user" retrying later
    check_studio_storage($ok === true, 'a second initialize() call still reports success');
    $secondMarker = file_get_contents($storage->root() . '/.initialized');
    check_studio_storage($firstMarker === $secondMarker, 'the original initialization marker is untouched by the second call — no reset', [$firstMarker, $secondMarker]);
}

// ── 5) Write/read roundtrip, and restart persistence across instances ─────
echo "\n5) identity read/write roundtrips, and survives a fresh instance (simulated request restart)\n";
{
    $storage = new StudioStorage();
    $storage->initialize(1);
    $ok = $storage->writeIdentity('{"deviceId":"abc123"}');
    check_studio_storage($ok === true, 'writeIdentity() succeeds once initialized');
    check_studio_storage($storage->readIdentity() === '{"deviceId":"abc123"}', 'readIdentity() returns exactly what was written, same instance');

    // A brand-new object has no in-memory state at all — this is the only
    // way restart persistence could be proven, since WordPress never keeps a
    // PHP object alive between requests either.
    $restarted = new StudioStorage();
    check_studio_storage($restarted->isInitialized(), 'a new StudioStorage instance still reports initialized — state lives on disk, not in the object');
    check_studio_storage($restarted->readIdentity() === '{"deviceId":"abc123"}', 'a new instance reads back the identical previously-written identity blob');
}

// ── 6) Invalid/oversized payloads are refused without touching prior data ─
echo "\n6) invalid or oversized payloads are refused, and never clobber the last valid value\n";
{
    $storage = new StudioStorage();
    $storage->initialize(1);
    $storage->writeIdentity('{"good":true}');

    check_studio_storage($storage->writeIdentity('{not json') === false, 'malformed JSON is refused');
    check_studio_storage($storage->readIdentity() === '{"good":true}', 'the previously-written good value is untouched after a refused malformed write');

    $oversized = str_repeat('a', 1_048_577);
    check_studio_storage($storage->writeIdentity('"' . $oversized . '"') === false, 'a payload over the 1 MiB cap is refused');
    check_studio_storage($storage->readIdentity() === '{"good":true}', 'the previously-written good value is untouched after a refused oversized write');

    check_studio_storage($storage->writeIdentity('null') === true, 'the literal JSON value null is accepted (valid JSON, not an error sentinel)');
}

// Fail-closed behaviour when the root cannot be made writable is covered by
// tests/studio-storage-unwritable-root.php — ABSPATH is a constant and
// cannot be redefined to a second, deliberately-locked location within this
// same process.

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage checks passed.\n";
