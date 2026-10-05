<?php

declare(strict_types=1);

// Runs the real CredentialRotation, CredentialCipher, ConnectionStore and
// ConnectorCredentials against an in-memory option boundary. Proves the key
// operations contract: replacing the key without a re-seal fails closed, the
// re-seal moves every secret to the new key bound to the same provider and
// field, it is all or nothing, and no key or plaintext reaches storage or the
// report.

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

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;

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

$oldRaw = random_bytes(32);
$newRaw = random_bytes(32);
$old = new CredentialCipher($oldRaw);
$new = new CredentialCipher($newRaw);
$store = new ConnectionStore();

$seed = static function () use ($store, $old): void {
    global $__options;
    $__options = [];
    $store->write('rezdy', ['config' => ['environment' => 'staging'], 'secrets' => [
        'api_key' => $old->seal(API_KEY, CredentialCipher::context('rezdy', 'api_key')),
    ]]);
    $store->write('other', ['config' => [], 'secrets' => [
        'token' => $old->seal(OTHER, CredentialCipher::context('other', 'token')),
    ]]);
};
$secret = static fn(CredentialCipher $cipher, string $provider, string $field): ?string
    => (new ConnectorCredentials($store, $cipher))->secret($provider, $field);
$leaks = static function (mixed $haystack) use ($oldRaw, $newRaw): bool {
    $text = serialize($haystack);
    foreach ([API_KEY, OTHER, $oldRaw, $newRaw, base64_encode($oldRaw), base64_encode($newRaw)] as $needle) {
        if (str_contains($text, $needle)) return true;
    }
    return false;
};

echo "Settings credential rotation\n";

// ── 1. Replacing the key without a re-seal fails closed ─────────────────
$seed();
check_rotation($secret($new, 'rezdy', 'api_key') === null, 'after swapping in a new key, an old envelope does not decrypt');
check_rotation(!(new ConnectorCredentials($store, $new))->hasSecret('rezdy', 'api_key'), 'an unreadable secret never reads as configured');
check_rotation($secret($old, 'rezdy', 'api_key') === API_KEY, 'the old key still opens it, so nothing was lost');

// ── 2. Re-seal ──────────────────────────────────────────────────────────
$seed();
$before = $store->read('rezdy');
$report = (new CredentialRotation($store, $old, $new))->reseal();
check_rotation($report['ok'] === true && $report['error'] === null, 're-seal succeeds with the previous and new keys');
check_rotation($report['resealed'] === ['rezdy:api_key', 'other:token'] && $report['already_current'] === [] && $report['unreadable'] === [], 'the report names every re-sealed provider:field slot');
check_rotation($secret($new, 'rezdy', 'api_key') === API_KEY && $secret($new, 'other', 'token') === OTHER, 'every secret now opens under the new key');
check_rotation($secret($old, 'rezdy', 'api_key') === null, 'the old key no longer opens anything');
$after = $store->read('rezdy');
check_rotation($after['secrets']['api_key']['kid'] === $new->keyId() && $after['secrets']['api_key']['nonce'] !== $before['secrets']['api_key']['nonce'], 'each envelope carries the new key id and a fresh nonce');
check_rotation($new->open($after['secrets']['api_key'], CredentialCipher::context('other', 'token')) === null, 'a re-sealed value stays bound to its own provider and field');
check_rotation($after['config'] === ['environment' => 'staging'] && $after['updated_at'] === $before['updated_at'], 're-seal leaves configuration and updated_at untouched');
check_rotation(!$leaks($__options), 'storage holds no plaintext and neither key after re-seal');
check_rotation(!$leaks($report), 'the report carries no plaintext and no key');
check_rotation(!str_contains(serialize($report), (string) $old->keyId()) && !str_contains(serialize($report), (string) $new->keyId()), 'the report carries no key id');

// ── 3. Idempotent ───────────────────────────────────────────────────────
$snapshot = serialize($__options);
$again = (new CredentialRotation($store, $old, $new))->reseal();
check_rotation($again['ok'] === true && $again['resealed'] === [] && $again['already_current'] === ['rezdy:api_key', 'other:token'], 'running the re-seal again reports every secret already current');
check_rotation(serialize($__options) === $snapshot, 'a repeated re-seal writes nothing');

// ── 4. All or nothing ───────────────────────────────────────────────────
$seed();
$foreign = new CredentialCipher(random_bytes(32));
$store->write('broken', ['config' => [], 'secrets' => [
    'token' => $foreign->seal('lost', CredentialCipher::context('broken', 'token')),
]]);
$snapshot = serialize($__options);
$partial = (new CredentialRotation($store, $old, $new))->reseal();
check_rotation($partial['ok'] === false && $partial['unreadable'] === ['broken:token'], 'a secret that opens under neither key is reported unreadable');
check_rotation($partial['resealed'] === [] && is_string($partial['error']), 'a failed re-seal reports nothing re-sealed and an error');
check_rotation(serialize($__options) === $snapshot, 'a failed re-seal writes nothing, so the readable secrets stay on the old key');
check_rotation(!(new ConnectorCredentials($store, $new))->hasSecret('broken', 'token') && !(new ConnectorCredentials($store, $new))->hasSecret('rezdy', 'api_key'), 'nothing unreadable is marked configured under the new key');

$seed();
$store->write('plain', ['config' => [], 'secrets' => ['token' => 'plaintext-value']]);
$plain = (new CredentialRotation($store, $old, $new))->reseal();
check_rotation($plain['ok'] === false && $plain['unreadable'] === ['plain:token'], 'a plaintext (non-envelope) value is unreadable, never sealed as if it were a secret');

// ── 5. Refusals before any work ─────────────────────────────────────────
$seed();
$snapshot = serialize($__options);
$noNew = (new CredentialRotation($store, $old, new CredentialCipher(null)))->reseal();
check_rotation($noNew['ok'] === false && str_contains((string) $noNew['error'], 'QSD_CREDENTIAL_KEY'), 'a missing new key is refused');
$noOld = (new CredentialRotation($store, new CredentialCipher(null), $new))->reseal();
check_rotation($noOld['ok'] === false && str_contains((string) $noOld['error'], CredentialRotation::PREVIOUS_CONSTANT), 'a missing previous key is refused');
$same = (new CredentialRotation($store, $old, new CredentialCipher($oldRaw)))->reseal();
check_rotation($same['ok'] === false && str_contains((string) $same['error'], 'same key'), 'the same key as previous and new is refused');
check_rotation(serialize($__options) === $snapshot, 'a refused rotation writes nothing');
check_rotation(!CredentialCipher::fromConstant(CredentialRotation::PREVIOUS_CONSTANT)->isAvailable(), 'no QSD_CREDENTIAL_KEY_PREVIOUS constant means no previous key');

echo "All Settings credential rotation checks passed.\n";
