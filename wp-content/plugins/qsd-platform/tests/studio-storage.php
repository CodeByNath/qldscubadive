<?php

declare(strict_types=1);

// StudioStorage: the host-private writable root for WEX's identity-space
// storage. Exercises the real class against a real temporary filesystem
// (only wp_upload_dir()/wp_json_encode() are stubbed) — isolation,
// fail-closed behaviour, idempotent initialization, and restart persistence
// are all genuine filesystem operations, not mocked.

$__uploadBase = sys_get_temp_dir() . '/qsd-studio-test-' . bin2hex(random_bytes(6));
mkdir($__uploadBase, 0755, true);

function wp_upload_dir(): array
{
    global $__uploadBase;
    return ['basedir' => $__uploadBase];
}
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
    $storage = new StudioStorage();
    check_studio_storage(!$storage->isInitialized(), 'isInitialized() is false before any action');
    check_studio_storage(!is_dir($storage->root()), 'the root directory does not exist merely from checking status — no silent preapproval');
    check_studio_storage($storage->readIdentity() === null, 'readIdentity() on an uninitialized root returns null');
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

// ── 7) Fail-closed when the resolved root cannot be made writable ─────────
echo "\n7) initialize() fails closed, never throws, when the root cannot be writable\n";
{
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
        echo "  skip — running as root; filesystem permission bits are not enforced, so this fail-closed path cannot be exercised here\n";
    } else {
        global $__uploadBase;
        $lockedBase = $__uploadBase . '-locked';
        mkdir($lockedBase, 0755, true);
        $lockedRoot = $lockedBase . '/qsd-studio-private';
        mkdir($lockedRoot, 0500, true); // exists, but not writable

        // wp_upload_dir() reads this global directly, so retargeting it is
        // enough to redirect the class without redefining the function.
        $__uploadBase = $lockedBase;
        $storage      = new StudioStorage();
        $ok           = $storage->initialize(1);
        check_studio_storage($ok === false, 'initialize() returns false (not an exception) when the pre-existing root directory is not writable');
        check_studio_storage(!is_file($lockedRoot . '/.initialized'), 'no marker is written when initialization failed');

        chmod($lockedRoot, 0755); // restore so cleanup can remove it
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage checks passed.\n";
