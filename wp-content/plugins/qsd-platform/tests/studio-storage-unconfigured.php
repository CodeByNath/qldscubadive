<?php

declare(strict_types=1);

// StudioStorage with NO QSD_STUDIO_PRIVATE_ROOT configured at all — the
// administrator has not (yet) confirmed and set a genuinely non-public
// path. Every operation must fail closed rather than guess a location, the
// way an earlier version inferred one from dirname(ABSPATH) and was wrong
// to. Split into its own process since the absence of the constant is
// itself the condition under test, and other files define it.

function wp_json_encode(mixed $value): string|false { return json_encode($value); }

require_once __DIR__ . '/../src/Modules/StudioHost/StudioStorage.php';

use QSD\Platform\Modules\StudioHost\StudioStorage;

$failures = [];
function check_studio_storage_unconfigured(bool $condition, string $label, mixed $detail = null): void
{
    global $failures;
    if ($condition) {
        echo "  ok — {$label}\n";
        return;
    }
    $failures[] = $label;
    echo '  FAIL — ' . $label . ($detail !== null ? ': ' . json_encode($detail) : '') . "\n";
}

echo "1) every operation fails closed when QSD_STUDIO_PRIVATE_ROOT is not defined\n";
{
    check_studio_storage_unconfigured(!defined('QSD_STUDIO_PRIVATE_ROOT'), 'precondition: the constant really is undefined in this process');

    $storage = new StudioStorage();
    check_studio_storage_unconfigured($storage->root() === '', 'root() returns empty — it never guesses a location from ABSPATH or anywhere else');
    check_studio_storage_unconfigured(!$storage->isInitialized(), 'isInitialized() is false');
    check_studio_storage_unconfigured($storage->initialize(1) === false, 'initialize() returns false, not an exception, with no configured root to provision');
    check_studio_storage_unconfigured($storage->readIdentity() === null, 'readIdentity() returns null');
    check_studio_storage_unconfigured($storage->writeIdentity('{"a":1}') === false, 'writeIdentity() refuses to write anywhere');
}

if ($failures !== []) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}
echo "\nStudio storage unconfigured-root checks passed.\n";
