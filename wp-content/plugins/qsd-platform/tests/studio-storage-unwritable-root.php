<?php

declare(strict_types=1);

// StudioStorage fail-closed path when its administrator-configured root
// cannot be made writable (e.g. permissions on an otherwise correctly
// non-public path). Split into its own process/file because
// QSD_STUDIO_PRIVATE_ROOT is a real PHP constant: it cannot be redefined to
// a second, deliberately-locked location within the same run, the way the
// other StudioStorage scenarios' fixtures can vary.

$__configuredRoot = sys_get_temp_dir() . '/qsd-studio-test-unwritable-' . bin2hex(random_bytes(6));
mkdir($__configuredRoot, 0755, true);
define('QSD_STUDIO_PRIVATE_ROOT', $__configuredRoot);

function wp_json_encode(mixed $value): string|false { return json_encode($value); }

require_once __DIR__ . '/../src/Modules/StudioHost/StudioStorage.php';

use QSD\Platform\Modules\StudioHost\StudioStorage;

$failures = [];
function check_studio_storage_locked(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

echo "1) initialize() fails closed, never throws, when the resolved root cannot be writable\n";
if (function_exists('posix_getuid') && posix_getuid() === 0) {
    echo "  skip — running as root; filesystem permission bits are not enforced, so this fail-closed path cannot be exercised here\n";
} else {
    $storage    = new StudioStorage();
    $lockedRoot = $storage->root();
    mkdir($lockedRoot, 0500, true); // exists ahead of time, but not writable

    $ok = $storage->initialize(1);
    check_studio_storage_locked($ok === false, 'initialize() returns false (not an exception) when the pre-existing root directory is not writable');
    check_studio_storage_locked(!is_file($lockedRoot . '/.initialized'), 'no marker is written when initialization failed');
    check_studio_storage_locked(!$storage->isInitialized(), 'isInitialized() still reports false after the failed attempt');

    chmod($lockedRoot, 0755); // restore so cleanup can remove it
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage unwritable-root checks passed.\n";
