<?php

declare(strict_types=1);

// Phase A foundation contract: identity bootstrap, resumability, no-lost-update
// compare-and-swap, and fail-closed storage. No WordPress is loaded: only the
// option API used by PlatformIdentifierStation's own registry is stubbed
// (mirrors tests/platform-identifier-station.php); AccountRepository talks to
// $wpdb directly, stubbed by tests/support-account-repository.php.
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
require_once __DIR__ . '/support-account-repository.php';

use QSD\Platform\Modules\Account\Support\AccountIdentity;
use QSD\Platform\Modules\Account\Support\AccountRepository;
use QSD\Platform\Modules\Account\Support\AccountSchema;
use QSD\Platform\Modules\Account\Support\AccountStorageBusy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierConflict;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierPolicy;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

function checkAccount(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

// ─── Scenario 1: no read-path minting ───────────────────────────────────────
$table = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table);
$station = new PlatformIdentifierStation();
$repository = new AccountRepository();
$identity = new AccountIdentity($station, $repository);

checkAccount($identity->isBootstrapped() === false, 'a fresh install is not bootstrapped');
checkAccount($identity->nodes() === array_fill_keys(AccountSchema::NODE_ORDER, null), 'nodes() reports all four as unbound');
checkAccount($table->rows === [], 'reading nodes() never writes the Account row');
checkAccount($GLOBALS['qsd_test_options'] === [], 'reading nodes() never writes the identity registry');

// ─── Scenario 2: idempotent bootstrap ───────────────────────────────────────
$first = $identity->bootstrap();
checkAccount(count(array_unique($first)) === 4, 'bootstrap mints four distinct platform ids');
foreach ($first as $entityType => $platformId) {
    checkAccount(PlatformIdentifierPolicy::validate($entityType, $platformId), "{$entityType} id matches its own policy prefix");
}
checkAccount($identity->isBootstrapped() === true, 'bootstrap leaves the hierarchy fully bound');

$second = $identity->bootstrap();
checkAccount($second === $first, 'a repeated bootstrap re-affirms the same four ids rather than re-minting');

// ─── Scenario 3: resumable interrupted bootstrap ────────────────────────────
// Fresh identity registry too: the fixed native-reference addresses are the
// same literal strings as Scenario 2's, so this simulates a second, separate
// site rather than colliding with the first one's reverse-key claims.
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$table2 = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table2);
$station2 = new PlatformIdentifierStation();
$repository2 = new AccountRepository();
$identity2 = new AccountIdentity($station2, $repository2);

// Simulate a request that died right after the first node bound.
$accountNative = AccountSchema::NATIVE_REFERENCES[PlatformIdentifierPolicy::ACCOUNT];
$partial = $station2->ensure(
    PlatformIdentifierPolicy::ACCOUNT,
    $accountNative,
    fn($native) => $repository2->readNodePlatformId(PlatformIdentifierPolicy::ACCOUNT),
    fn($native, $platformId) => $repository2->writeNodePlatformId(PlatformIdentifierPolicy::ACCOUNT, $platformId)
);
checkAccount(
    $repository2->readNodePlatformId(PlatformIdentifierPolicy::ACCOUNT) === $partial->platformId(),
    'the interrupted run left the first node durably bound'
);
checkAccount($identity2->isBootstrapped() === false, 'the other three nodes are still unbound after the interruption');

$resumed = $identity2->bootstrap();
checkAccount(
    $resumed[PlatformIdentifierPolicy::ACCOUNT] === $partial->platformId(),
    "resuming bootstrap keeps the already-bound node's id unchanged"
);
checkAccount($identity2->isBootstrapped() === true, 'resuming bootstrap completes every remaining node');

// ─── Scenario 4: no lost update under a forced compare-and-swap race ───────
$table3 = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table3);
$repository3 = new AccountRepository();

// The mutator's first invocation plants a write directly on the table — as if
// a second connection's commit landed between our read and our write — then
// returns a mutation computed from the state it actually read (which does not
// yet include that write). commit() must detect the resulting stale-bytes
// mismatch, retry against the now-current row, and re-apply its own mutation
// on top of it, losing neither write.
$interfered = false;
$committed = $repository3->commit(function (array $state) use (&$interfered, $table3): array {
    if (!$interfered) {
        $interfered = true;
        $racingState = AccountSchema::defaultState();
        $racingState['nodes'][PlatformIdentifierPolicy::ACCOUNT]['platform_id'] = 'QSDA22222';
        $table3->rows[AccountSchema::OPTION_KEY] = (string) json_encode($racingState);
    }
    $state['nodes'][PlatformIdentifierPolicy::ACCOUNT_SETTINGS]['platform_id'] = 'QSDAS22222';
    return $state;
});

checkAccount($interfered === true, 'the simulated race actually landed before our own write attempt');
checkAccount(
    $committed['nodes'][PlatformIdentifierPolicy::ACCOUNT]['platform_id'] === 'QSDA22222'
        && $committed['nodes'][PlatformIdentifierPolicy::ACCOUNT_SETTINGS]['platform_id'] === 'QSDAS22222',
    "retrying against the fresh row preserves the racing writer's change and still lands our own"
);

// ─── Scenario 5: fail-closed storage ────────────────────────────────────────
// Seed an existing row first so commit() takes the compare-and-swap UPDATE
// path (an empty table would instead take the one-shot INSERT IGNORE path,
// which alwaysStale does not govern).
$table4 = new AccountOptionsTable();
$table4->rows[AccountSchema::OPTION_KEY] = (string) json_encode(AccountSchema::defaultState());
$originalRow = $table4->rows[AccountSchema::OPTION_KEY];
$table4->alwaysStale = true;
$GLOBALS['wpdb'] = new AccountWpdb($table4);
$repository4 = new AccountRepository();

$busy = false;
try {
    $repository4->commit(static function (array $state): array {
        $state['nodes'][PlatformIdentifierPolicy::ACCOUNT]['platform_id'] = 'QSDA33333';
        return $state;
    });
} catch (AccountStorageBusy) {
    $busy = true;
}
checkAccount($busy, 'exhausting the compare-and-swap retry budget fails closed rather than looping forever or writing silently');
checkAccount($table4->rows[AccountSchema::OPTION_KEY] === $originalRow, 'a failed-closed commit leaves the row exactly as it was, no partial write');

// ─── Scenario 6: recovery from a reserved-but-not-bound node ───────────────
// Simulates a request that died inside PlatformIdentifierStation::assign(),
// after it wrote the native storage slot but before it flipped the registry's
// forward record from 'reserved' to 'bound'. A plain ensure() would see the
// stored id and reject it as "already reserved, retired, or deleted"; this
// must instead finish the bind with assign() and resume normally.
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$table5 = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table5);
$station5 = new PlatformIdentifierStation();
$repository5 = new AccountRepository();
$identity5 = new AccountIdentity($station5, $repository5);

$accountEntityType = PlatformIdentifierPolicy::ACCOUNT;
$accountNative5 = AccountSchema::NATIVE_REFERENCES[$accountEntityType];
$reservation5 = $station5->reserve($accountEntityType);
$repository5->writeNodePlatformId($accountEntityType, $reservation5->platformId(), null);

checkAccount(
    $station5->resolve($reservation5->platformId())?->status() === PlatformIdentifierStation::STATUS_RESERVED,
    'the simulated interruption leaves the forward record reserved, not bound'
);

$recovered = $identity5->bootstrap();
checkAccount(
    $recovered[$accountEntityType] === $reservation5->platformId(),
    'the interrupted reservation is completed in place, never re-minted'
);
checkAccount(
    $station5->resolve($reservation5->platformId())?->status() === PlatformIdentifierStation::STATUS_BOUND,
    'the recovered node is now bound'
);
checkAccount($identity5->isBootstrapped() === true, 'bootstrap completes every remaining node after recovering the interrupted one');

// ─── Scenario 7: parent linkage mismatch is caught, not silently chained ───
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$table6 = new AccountOptionsTable();
$GLOBALS['wpdb'] = new AccountWpdb($table6);
$station6 = new PlatformIdentifierStation();
$repository6 = new AccountRepository();
$identity6 = new AccountIdentity($station6, $repository6);

$identity6->bootstrap();

// Corrupt Settings' recorded parent independently of the registry, as if the
// aggregate had been edited outside AccountIdentity's own write path.
[$state6] = $repository6->read();
$state6['nodes'][PlatformIdentifierPolicy::ACCOUNT_SETTINGS]['parent_platform_id'] = 'QSDA-not-the-real-parent';
$table6->rows[AccountSchema::OPTION_KEY] = (string) json_encode($state6);

$mismatchCaught = false;
try {
    $identity6->bootstrap();
} catch (PlatformIdentifierConflict) {
    $mismatchCaught = true;
}
checkAccount($mismatchCaught, 'a node naming a different parent than the current bootstrap order is rejected, not silently re-chained');

// ─── Scenario 8: a node bound before parent linkage existed is not flagged ─
// Mirrors a node bootstrapped by a prior version of this file that never
// persisted parent_platform_id at all (key absent, not merely null).
$GLOBALS['qsd_test_options'] = [];
$GLOBALS['qsd_test_autoload'] = [];
$table7 = new AccountOptionsTable();
$legacyState = AccountSchema::defaultState();
unset($legacyState['nodes'][PlatformIdentifierPolicy::ACCOUNT]['parent_platform_id']);
$table7->rows[AccountSchema::OPTION_KEY] = (string) json_encode($legacyState);
$GLOBALS['wpdb'] = new AccountWpdb($table7);
$station7 = new PlatformIdentifierStation();
$repository7 = new AccountRepository();
$identity7 = new AccountIdentity($station7, $repository7);

checkAccount(
    $repository7->readNodeParentPlatformId(PlatformIdentifierPolicy::ACCOUNT) === null,
    'a legacy row with no recorded parent reads as not-recorded, not as a mismatch'
);
$legacyBound = $identity7->bootstrap();
checkAccount(count(array_unique($legacyBound)) === 4, 'bootstrapping a legacy row with no parent metadata still completes cleanly');

// ─── Scenario 9: an idempotent commit succeeds without ever writing ───────
// A real MySQL UPDATE whose SET value matches the row's current bytes
// reports 0 affected rows even though the WHERE matched (no CLIENT_FOUND_ROWS).
// AccountRepository::commit() must recognise a no-op mutation before
// attempting that write, not treat the resulting 0 as a lost race — the exact
// staging defect (a repeat/idempotent Brand Save 503ing after exhausting the
// retry budget against an always-identical row).
$table8 = new AccountOptionsTable();
$table8->rows[AccountSchema::OPTION_KEY] = (string) json_encode(AccountSchema::defaultState());
$GLOBALS['wpdb'] = new AccountWpdb($table8);
$repository8 = new AccountRepository();

// Every UPDATE this stub receives reports 0 affected rows, exactly as a real
// no-op UPDATE would — if commit() ever reached the query for a true no-op,
// it would spin through every attempt and throw, same as a real permanently
// busy row.
$table8->alwaysStale = true;

$noopCommitSucceeded = true;
try {
    $repository8->commit(static fn(array $state): array => $state);
} catch (AccountStorageBusy) {
    $noopCommitSucceeded = false;
}
checkAccount($noopCommitSucceeded, 'a commit whose mutator changes nothing succeeds without ever attempting the always-failing write');

$realChangeFailedClosed = false;
try {
    $repository8->commit(static function (array $state): array {
        $state['brand']['name'] = 'Changed';
        return $state;
    });
} catch (AccountStorageBusy) {
    $realChangeFailedClosed = true;
}
checkAccount($realChangeFailedClosed, 'a commit that genuinely changes the row still fails closed against a permanently stale row — the no-op path is not a blanket bypass');

echo "Account Station foundation: PASS\n";
