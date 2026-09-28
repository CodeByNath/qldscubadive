<?php

declare(strict_types=1);

// Standalone Phase 1 contract test. No WordPress or domain entity is loaded:
// only the option API used by the Platform-owned identity registry is stubbed.
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];

function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool
{
    if (array_key_exists($key, $GLOBALS['qsd_test_options'])) {
        return false;
    }
    $GLOBALS['qsd_test_options'][$key] = $value;
    $GLOBALS['qsd_test_autoload'][$key] = $autoload;
    return true;
}

function get_option(string $key, mixed $default = false): mixed
{
    return $GLOBALS['qsd_test_options'][$key] ?? $default;
}

function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool
{
    $changed = !array_key_exists($key, $GLOBALS['qsd_test_options']) || $GLOBALS['qsd_test_options'][$key] !== $value;
    $GLOBALS['qsd_test_options'][$key] = $value;
    if ($autoload !== null) {
        $GLOBALS['qsd_test_autoload'][$key] = $autoload;
    }
    return $changed;
}

require_once __DIR__ . '/autoload.php';

use QSD\Platform\PlatformIdentifier\PlatformIdentifierConflict;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierPolicy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

function checkIdentifier(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

function expectIdentifierConflict(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PlatformIdentifierConflict) {
        echo "  ok — {$message}\n";
        return;
    }
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

/** @return callable(int, int): int */
function identifierRandom(string $characters): callable
{
    $indexes = array_map(
        static fn(string $character): int => (int) strpos(PlatformIdentifierPolicy::ALPHABET, $character),
        str_split($characters)
    );
    $offset = 0;

    return static function (int $minimum, int $maximum) use (&$indexes, &$offset): int {
        if (!isset($indexes[$offset])) {
            return 0;
        }
        return $indexes[$offset++];
    };
}

$expected = [
    'service'  => 'QSDS',
    'category' => 'QSDC',
];

checkIdentifier(PlatformIdentifierPolicy::prefixes() === $expected, 'every entity prefix is locked');
checkIdentifier(strlen(PlatformIdentifierPolicy::ALPHABET) === 30, 'alphabet has thirty unambiguous characters');
checkIdentifier(strpbrk(PlatformIdentifierPolicy::ALPHABET, '01ILOU') === false, 'alphabet excludes ambiguous characters');

foreach ($expected as $entityType => $prefix) {
    checkIdentifier(PlatformIdentifierPolicy::validate($entityType, $prefix . '2A7KZ'), "{$entityType} validates its exact prefix and suffix");
    checkIdentifier(!PlatformIdentifierPolicy::validate($entityType, $prefix . '2A7K'), "{$entityType} rejects a short suffix");
    checkIdentifier(!PlatformIdentifierPolicy::validate($entityType, $prefix . '2A7K0'), "{$entityType} rejects an ambiguous suffix");
}
checkIdentifier(PlatformIdentifierPolicy::entityTypeFor('QSDC2A7KZ') === 'category' && PlatformIdentifierPolicy::entityTypeFor('QSDS2A7KZ') === 'service', 'each prefix resolves to exactly its own entity type');
checkIdentifier(PlatformIdentifierPolicy::entityTypeFor('QSDX2A7KZ') === null, 'an unknown prefix resolves to no entity type');

$station = new PlatformIdentifierStation(identifierRandom('7K9Q2'));
$generated = $station->generate(PlatformIdentifierPolicy::SERVICE);
checkIdentifier($generated->value() === 'QSDS7K9Q2', 'generate uses the locked policy and injected secure-random seam');

$station = new PlatformIdentifierStation(identifierRandom('2222233333'));
$first = $station->reserve(PlatformIdentifierPolicy::SERVICE);
$second = $station->reserve(PlatformIdentifierPolicy::SERVICE);
checkIdentifier($first->platformId() === 'QSDS22222', 'first candidate reserves atomically');
checkIdentifier($second->platformId() === 'QSDS33333', 'registry collision generates a different candidate');
checkIdentifier(!in_array(true, $GLOBALS['qsd_test_autoload'], true) && !in_array('yes', $GLOBALS['qsd_test_autoload'], true), 'registry options are non-autoloaded');

$station = new PlatformIdentifierStation(identifierRandom('4444455555'));
$legacyChecked = [];
$reservation = $station->reserve(
    PlatformIdentifierPolicy::CATEGORY,
    static function (string $candidate) use (&$legacyChecked): bool {
        $legacyChecked[] = $candidate;
        return $candidate === 'QSDC44444';
    }
);
checkIdentifier($reservation->platformId() === 'QSDC55555', 'authoritative unregistered collision is retired and retried');
checkIdentifier($station->resolve('QSDC44444')?->status() === PlatformIdentifierStation::STATUS_RETIRED, 'colliding historical reservation remains retired');

$entityStorage = [];
$read = static function (int|string $native) use (&$entityStorage): string {
    return $entityStorage[$native] ?? '';
};
$write = static function (int|string $native, string $platformId) use (&$entityStorage): void {
    $entityStorage[$native] = $platformId;
};

$binding = $station->assign($reservation, 184, $read, $write);
checkIdentifier($binding->nativeReference() === 184 && $binding->platformId() === 'QSDC55555', 'assign binds a reservation to a native reference');
checkIdentifier($station->resolve('QSDC55555')?->nativeReference() === 184, 'forward lookup resolves the native reference');
checkIdentifier($station->lookupNative(PlatformIdentifierPolicy::CATEGORY, 184)?->platformId() === 'QSDC55555', 'reverse lookup resolves the Platform ID');

$same = $station->ensure(PlatformIdentifierPolicy::CATEGORY, 184, $read, $write);
checkIdentifier($same->platformId() === 'QSDC55555', 'ensure preserves a valid existing identifier');

$entityStorage[185] = 'QSDC55555';
expectIdentifierConflict(
    static fn() => $station->ensure(PlatformIdentifierPolicy::CATEGORY, 185, $read, $write),
    'one Platform ID cannot bind to two native entities'
);
$entityStorage[186] = 'not-valid';
expectIdentifierConflict(
    static fn() => $station->ensure(PlatformIdentifierPolicy::CATEGORY, 186, $read, $write),
    'invalid existing identity fails closed'
);
$entityStorage[188] = ' QSDC2A7KZ';
expectIdentifierConflict(
    static fn() => $station->ensure(PlatformIdentifierPolicy::CATEGORY, 188, $read, $write),
    'authoritative values are validated exactly without trimming'
);

$anotherStation = new PlatformIdentifierStation(identifierRandom('66666'));
$secondReservation = $anotherStation->reserve(PlatformIdentifierPolicy::CATEGORY);
$entityStorage[187] = 'QSDC77777';
expectIdentifierConflict(
    static fn() => $anotherStation->assign($secondReservation, 187, $read, $write),
    'assignment refuses to overwrite a different non-empty identity'
);
$anotherStation->retire($secondReservation);
checkIdentifier($anotherStation->resolve('QSDC66666')?->status() === PlatformIdentifierStation::STATUS_RETIRED, 'abandoned reservation remains permanently retired');

$station->markDeleted(PlatformIdentifierPolicy::CATEGORY, 184);
checkIdentifier($station->resolve('QSDC55555')?->isDeleted() === true, 'permanent deletion retains a forward tombstone');
checkIdentifier($station->lookupNative(PlatformIdentifierPolicy::CATEGORY, 184)?->isDeleted() === true, 'permanent deletion retains a reverse tombstone');
expectIdentifierConflict(
    static fn() => $station->ensure(PlatformIdentifierPolicy::CATEGORY, 184, $read, $write),
    'ensure cannot resurrect a deleted binding'
);

$batchStorage = [1 => '', 2 => 'QSDS2A7KZ', 3 => 'invalid'];
$batchStation = new PlatformIdentifierStation(identifierRandom('88888'));
$batchRead = static function (int|string $native) use (&$batchStorage): string {
    return $batchStorage[$native] ?? '';
};
$batch = $batchStation->assignExistingBatch(
    PlatformIdentifierPolicy::SERVICE,
    null,
    3,
    static fn(int|string|null $cursor, int $limit): array => [
        'items' => [1, 2, 3],
        'next_cursor' => 3,
        'complete' => true,
    ],
    $batchRead,
    static function (int|string $native, string $platformId) use (&$batchStorage): void {
        $batchStorage[$native] = $platformId;
    }
);
checkIdentifier($batch->processed() === 3, 'batch assignment remains bounded by the requested page');
checkIdentifier($batch->assigned() === 1 && $batch->preserved() === 1, 'batch reports assigned and preserved identities');
checkIdentifier(count($batch->conflicts()) === 1, 'batch reports invalid identity conflicts without replacing them');
checkIdentifier($batch->complete() && $batch->nextCursor() === 3, 'batch returns completion and next cursor');

echo "Platform Identifier Station contract: PASS\n";
