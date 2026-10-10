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

echo "Account Station foundation: PASS\n";
