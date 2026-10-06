<?php

declare(strict_types=1);

// Runs the real CredentialRotation, CredentialKeyring, CredentialCipher,
// ConnectionStore and ConnectorCredentials against an in-memory option
// boundary. Proves the QSD-owned rotation contract: a new data key is
// generated, every secret moves to it bound to the same provider and field,
// it is all or nothing, the old generation is retired only afterwards, and no
// key, wrapped key, key id or plaintext reaches the report. Also proves the
// site's WordPress secret keys are enough to make secure storage available.

$__options = [];

function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool {
    global $__options;
    if (array_key_exists($key, $__options)) return false;
    $__options[$key] = $value;
    return true;
}
function get_option(string $key, mixed $default = false): mixed {
    global $__options;
    return array_key_exists($key, $__options) ? $__options[$key] : $default;
}
function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool {
    global $__options;
    $__options[$key] = $value;
    return true;
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/support-credential-guard.php';
$wpdb = new GuardWpdb();

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialKeyring;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;
use QSD\Platform\Modules\Settings\Security\WpdbCredentialMutationGuard;

function check_rotation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

const API_KEY = 'rz-live-ROTATE-41d0';
const OTHER   = 'other-SECRET-7b2e';

$wrapRaw = random_bytes(32);
$keyring = new CredentialKeyring([new CredentialCipher($wrapRaw)]);
$store = new ConnectionStore();
$guard = new WpdbCredentialMutationGuard(null, null, 0);
$rotation = new CredentialRotation($store, $keyring, $guard);
$credentials = new ConnectorCredentials($store, $keyring);

$seed = static function () use ($store, $keyring): void {
    global $__options;
    $__options = [];
    $cipher = $keyring->sealingCipher();
    $store->write('rezdy', ['config' => ['environment' => 'staging'], 'secrets' => [
        'api_key' => $cipher->seal(API_KEY, CredentialCipher::context('rezdy', 'api_key')),
    ]]);
    $store->write('other', ['config' => [], 'secrets' => [
        'token' => $cipher->seal(OTHER, CredentialCipher::context('other', 'token')),
    ]]);
};
$ringOf = static fn(): array => $GLOBALS['__options'][CredentialKeyring::OPTION];
$leaks = static function (mixed $haystack) use ($wrapRaw): bool {
    $text = serialize($haystack);
    foreach ([API_KEY, OTHER, $wrapRaw, base64_encode($wrapRaw)] as $needle) {
        if (str_contains($text, $needle)) return true;
    }
    return false;
};

echo "Settings credential rotation\n";

// ── 1. Rotate ───────────────────────────────────────────────────────────
$seed();
$before = $store->read('rezdy');
$oldKid = $ringOf()['active'];
$report = $rotation->rotate();
check_rotation($report['ok'] === true && $report['error'] === null, 'rotation succeeds with no key material supplied');
check_rotation($report['resealed'] === ['rezdy:api_key', 'other:token'] && $report['unreadable'] === [], 'the report names every re-sealed provider:field slot');
$ring = $ringOf();
check_rotation($ring['active'] !== $oldKid && array_keys($ring['keys']) === [$ring['active']], 'a new data key is active and the old generation is retired');
check_rotation($credentials->secret('rezdy', 'api_key') === API_KEY && $credentials->secret('other', 'token') === OTHER, 'every secret opens under the new data key');
$after = $store->read('rezdy');
check_rotation($after['secrets']['api_key']['kid'] === $ring['active'] && $after['secrets']['api_key']['nonce'] !== $before['secrets']['api_key']['nonce'], 'each envelope carries the new key id and a fresh nonce');
check_rotation($keyring->cipher()->open($after['secrets']['api_key'], CredentialCipher::context('other', 'token')) === null, 'a re-sealed value stays bound to its own provider and field');
check_rotation($after['config'] === ['environment' => 'staging'] && $after['updated_at'] === $before['updated_at'], 'rotation leaves configuration and updated_at untouched');
check_rotation(!$leaks($__options), 'storage holds no plaintext and no wrapping key after rotation');
check_rotation(!$leaks($report) && !str_contains(serialize($report), $oldKid) && !str_contains(serialize($report), $ring['active']), 'the report carries no plaintext, key or key id');
check_rotation($rotation->inspect() === ['available' => true, 'generations' => 1, 'current' => ['rezdy:api_key', 'other:token'], 'previous' => [], 'unreadable' => []], 'inspect reports every secret on the active key, read-only');

// ── 2. Repeatable ───────────────────────────────────────────────────────
$kid = $ringOf()['active'];
$again = $rotation->rotate();
check_rotation($again['ok'] && $ringOf()['active'] !== $kid && $credentials->secret('rezdy', 'api_key') === API_KEY, 'rotating again moves to a further key and every secret still opens');

// ── 3. All or nothing ───────────────────────────────────────────────────
$seed();
$foreign = new CredentialCipher(random_bytes(32));
$store->write('broken', ['config' => [], 'secrets' => [
    'token' => $foreign->seal('lost', CredentialCipher::context('broken', 'token')),
]]);
$snapshot = serialize($__options);
$partial = $rotation->rotate();
check_rotation($partial['ok'] === false && $partial['unreadable'] === ['broken:token'], 'a secret that opens under no generation is reported unreadable');
check_rotation($partial['resealed'] === [] && is_string($partial['error']) && !str_contains($partial['error'], 'wp-config'), 'a failed rotation reports nothing re-sealed and a plain error with no server step');
check_rotation(serialize($__options) === $snapshot, 'a failed rotation writes nothing: no new generation, no re-sealed secret');
check_rotation($credentials->secret('broken', 'token') === null && $credentials->secret('rezdy', 'api_key') === API_KEY, 'nothing unreadable is marked configured, and readable secrets are unchanged');

$seed();
$store->write('plain', ['config' => [], 'secrets' => ['token' => 'plaintext-value']]);
$plain = $rotation->rotate();
check_rotation($plain['ok'] === false && $plain['unreadable'] === ['plain:token'], 'a plaintext (non-envelope) value is unreadable, never sealed as if it were a secret');

// ── 4. Interrupted between steps: still openable ────────────────────────
$seed();
$keyring->stage(random_bytes(32)); // the new generation is stored, the secrets not yet moved
check_rotation($credentials->secret('rezdy', 'api_key') === API_KEY && $rotation->inspect()['previous'] === ['rezdy:api_key', 'other:token'], 'after the staging step alone, every secret still opens under the kept generation');
check_rotation($rotation->rotate()['ok'] && count($ringOf()['keys']) === 1 && $credentials->secret('other', 'token') === OTHER, 'the next rotation completes and retires every older generation');

// ── 5. A save between planning and writing is kept ──────────────────────
$seed();
$planned = ['rezdy' => ['api_key' => $store->read('rezdy')['secrets']['api_key']]];
$fresh = $keyring->sealingCipher()->seal('rz-NEWER-KEY', CredentialCipher::context('rezdy', 'api_key'));
$store->write('rezdy', ['config' => ['environment' => 'staging'], 'secrets' => ['api_key' => $fresh]]);
$store->replaceSecrets(['rezdy' => ['api_key' => ['stale' => true]]], $planned);
check_rotation($store->read('rezdy')['secrets']['api_key'] === $fresh, 'a slot saved after it was planned is not overwritten by the rotation write');

// ── 5b. The guard: busy means nothing changes; a bypassing write is never retired ──
$seed();
$snapshot = serialize($__options);
$busy = null;
$guard->hold(static function () use ($rotation, &$busy): void { $busy = $rotation->rotate(); });
check_rotation($busy['ok'] === false && str_contains((string) $busy['error'], 'in progress') && serialize($__options) === $snapshot, 'while another credential change holds the guard, rotation writes nothing and reports failure');
check_rotation(!isset($wpdb->rows[WpdbCredentialMutationGuard::ROW]), 'the guard row is gone once every holder has finished');

// Defence in depth: a write that bypassed the guard and landed under the old
// generation after replacement makes rotation fail without retiring anything.
$seed();
$oldCipher = $keyring->cipher();
$oldKid = $ringOf()['active'];
$bypass = new CredentialRotation($store, $keyring, $guard, static function (string $phase) use ($store, $oldCipher): void {
    if ($phase === 'replaced') {
        $store->write('rezdy', ['config' => ['environment' => 'staging'], 'secrets' => [
            'api_key' => $oldCipher->seal('rz-BYPASS', CredentialCipher::context('rezdy', 'api_key')),
        ]]);
    }
});
$bypassReport = $bypass->rotate();
check_rotation($bypassReport['ok'] === false && str_contains((string) $bypassReport['error'], 'No older key was retired'), 'rotation never reports success when a stored key is not on the new key');
check_rotation(isset($ringOf()['keys'][$oldKid]) && $credentials->secret('rezdy', 'api_key') === 'rz-BYPASS' && $credentials->secret('other', 'token') === OTHER, 'no generation was retired and every key still opens');
check_rotation(!$leaks($bypassReport) && !str_contains(serialize($bypassReport), $oldKid), 'the failure report carries no secret, key or key id');
check_rotation($rotation->rotate()['ok'] && count($ringOf()['keys']) === 1 && $credentials->secret('rezdy', 'api_key') === 'rz-BYPASS', 'the next rotation completes');

// Retirement itself never drops a generation a stored secret names.
$seed();
$oldKid = $ringOf()['active'];
$keyring->stage(random_bytes(32));
$keyring->retireUnreferenced([$oldKid]);
check_rotation(isset($ringOf()['keys'][$oldKid]) && $credentials->secret('rezdy', 'api_key') === API_KEY, 'retirement keeps any generation a stored secret still names');

// ── 6. Refusal before any work ──────────────────────────────────────────
$seed();
$snapshot = serialize($__options);
$unavailable = (new CredentialRotation($store, new CredentialKeyring([]), $guard))->rotate();
check_rotation($unavailable['ok'] === false && str_contains((string) $unavailable['error'], 'Secure storage is unavailable'), 'with no wrapping key, rotation is refused in plain words');
check_rotation(serialize($__options) === $snapshot, 'a refused rotation writes nothing');

// ── 7. WordPress secret keys alone make storage available ───────────────
define('SECURE_AUTH_KEY', str_repeat('k', 64));
define('SECURE_AUTH_SALT', str_repeat('s', 64));
$__options = [];
$siteRing = CredentialKeyring::fromEnvironment();
$sealed = $siteRing->sealingCipher()->seal(API_KEY, CredentialCipher::context('rezdy', 'api_key'));
check_rotation($siteRing->isAvailable() && CredentialKeyring::fromEnvironment()->cipher()->open($sealed, CredentialCipher::context('rezdy', 'api_key')) === API_KEY, 'with only the standard WordPress secret keys, the first save works and the key reopens on the next request');
check_rotation(!$leaks($__options) && !str_contains(serialize($__options), str_repeat('k', 64)) && !str_contains(serialize($__options), CredentialKeyring::derive(SECURE_AUTH_KEY . "\0" . SECURE_AUTH_SALT)), 'neither the WordPress secret keys nor the derived wrapping key is stored');

echo "All Settings credential rotation checks passed.\n";
