<?php

declare(strict_types=1);

// Runs the real Settings Connections controller, store, credential capability
// and Rezdy connector seam against an in-memory option boundary. Proves the
// secret contract: a secret enters on save, is readable server-side through
// ConnectorCredentials only, and never appears in any REST projection, and
// that changing a secret needs administrator authority (manage_options). The
// QSD keyring needs no setup: the first save creates its data key, and a
// changed wrapping key fails closed without deleting anything.

$__settingsOptions = [];
$__routes = [];
// The current user's capabilities. An administrator also holds manage_qsd
// (PlatformAccess grants it); a business platform manager holds only manage_qsd.
const ADMINISTRATOR = ['manage_qsd', 'manage_options'];
const PLATFORM_MANAGER = ['manage_qsd'];
$__caps = ADMINISTRATOR;

function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function add_action(string $hook, callable $callback): void {}
function register_rest_route(string $namespace, string $route, array $args): bool {
    global $__routes;
    $__routes[$route] = $args;
    return true;
}
function current_user_can(string $cap): bool { global $__caps; return in_array($cap, $__caps, true); }
function add_option(string $key, mixed $value, string $deprecated = '', string|bool $autoload = 'yes'): bool {
    global $__settingsOptions;
    if (array_key_exists($key, $__settingsOptions)) return false;
    $__settingsOptions[$key] = ['value' => $value, 'autoload' => $autoload];
    return true;
}
function get_option(string $key, mixed $default = false): mixed {
    global $__settingsOptions;
    return array_key_exists($key, $__settingsOptions) ? $__settingsOptions[$key]['value'] : $default;
}
function update_option(string $key, mixed $value, string|bool|null $autoload = null): bool {
    global $__settingsOptions;
    $__settingsOptions[$key]['value'] = $value;
    return true;
}
function rest_ensure_response(mixed $value): WP_REST_Response {
    return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value, 200);
}

class WP_REST_Request {
    public function __construct(private array $params = []) {}
    public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
    public function get_json_params(): array { return $this->params; }
}
class WP_REST_Response {
    public function __construct(private mixed $data, private int $status) {}
    public function get_data(): mixed { return $this->data; }
    public function get_status(): int { return $this->status; }
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/support-credential-guard.php';
$wpdb = new GuardWpdb();

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialKeyring;
use QSD\Platform\Modules\Settings\Security\CredentialMutationBusy;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;
use QSD\Platform\Modules\Settings\Security\WpdbCredentialMutationGuard;

function check_settings(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

const SECRET = 'rz-live-SECRET-9f3a7c';

$store = new ConnectionStore();
$wrapRaw = random_bytes(32);
$keyring = new CredentialKeyring([new CredentialCipher($wrapRaw)]);
$guard = new WpdbCredentialMutationGuard(null, null, 0);
$controller = new SettingsConnectionsController($store, $keyring, $guard);
$controller->registerRoutes();
$credentials = new ConnectorCredentials($store, $keyring);
$rezdy = new RezdyConnector($credentials);

$encoded = static fn(WP_REST_Response $response): string => json_encode($response->get_data());

echo "Settings Connections\n";

check_settings(isset($__routes['/admin/settings/connections']) && isset($__routes['/admin/settings/connections/(?P<provider>[a-z0-9-]+)']), 'list and per-provider routes are registered');
$gates = [];
foreach ($__routes as $route => $args) {
    $endpoints = isset($args['methods']) ? [$args] : $args;
    foreach ($endpoints as $endpoint) {
        $gates[$endpoint['methods']] = $endpoint['permission_callback'] ?? null;
    }
}
check_settings($gates === [
    'GET'    => [$controller, 'requireAdmin'],
    'PUT'    => [$controller, 'requireSaveAuthority'],
    'DELETE' => [$controller, 'requireSecretAuthority'],
], 'GET is gated by requireAdmin, PUT by requireSaveAuthority, DELETE by requireSecretAuthority');
check_settings($controller->requireAdmin() === true, 'a manage_qsd user passes the gate');
$__caps = [];
check_settings($controller->requireAdmin() === false, 'a user without manage_qsd is refused');

// ── Secret authority: administrator only ─────────────────────────────────
$put = static fn(array $params): WP_REST_Request => new WP_REST_Request(['provider' => 'rezdy'] + $params);
$__caps = PLATFORM_MANAGER;
check_settings($controller->requireAdmin() === true, 'a platform manager may read safe connection state');
check_settings($controller->requireSaveAuthority($put(['values' => ['environment' => 'staging']])) === true, 'a platform manager may save non-secret configuration');
check_settings($controller->requireSaveAuthority($put(['values' => ['environment' => 'staging'], 'secrets' => ['api_key' => '']])) === true, 'an empty secret (keep the stored value) is not a secret change');
check_settings($controller->requireSaveAuthority($put(['secrets' => ['api_key' => SECRET]])) === false, 'a platform manager may not set a secret');
check_settings($controller->requireSaveAuthority($put(['values' => ['environment' => 'staging'], 'secrets' => ['api_key' => SECRET]])) === false, 'a secret hidden beside configuration is still refused');
check_settings($controller->requireSaveAuthority($put(['secrets' => ['api_key' => ['nested']]])) === false, 'a malformed secret value is treated as a secret change');
check_settings($controller->requireSaveAuthority($put(['clear' => ['api_key']])) === false, 'a platform manager may not clear a secret');
check_settings($controller->requireSecretAuthority() === false, 'a platform manager may not disconnect');
check_settings($controller->listConnections(new WP_REST_Request())->get_data()['permissions'] === ['manage_secrets' => false], 'the list tells a platform manager they cannot manage secrets');
$__caps = ['manage_options'];
check_settings($controller->requireSaveAuthority($put(['secrets' => ['api_key' => SECRET]])) === false && $controller->requireSecretAuthority() === false, 'manage_options without manage_qsd still fails the platform gate');
$__caps = ADMINISTRATOR;
check_settings($controller->requireSaveAuthority($put(['secrets' => ['api_key' => SECRET]])) === true, 'an administrator may set a secret');
check_settings($controller->requireSaveAuthority($put(['clear' => ['api_key']])) === true, 'an administrator may clear a secret');
check_settings($controller->requireSecretAuthority() === true, 'an administrator may disconnect');
check_settings($controller->listConnections(new WP_REST_Request())->get_data()['permissions'] === ['manage_secrets' => true], 'the list tells an administrator they can manage secrets');

$list = $controller->listConnections(new WP_REST_Request());
$rezdyRow = $list->get_data()['connections'][0] ?? [];
check_settings(count($list->get_data()['connections']) === 1 && $rezdyRow['provider'] === 'rezdy', 'Rezdy is the one registered provider');
check_settings($rezdyRow['state'] === 'not_configured' && $rezdyRow['updated_at'] === null, 'an unsaved provider reads not_configured');
check_settings($list->get_data()['encryption'] === ['available' => true] && !isset($__settingsOptions[CredentialKeyring::OPTION]), 'secure storage is available before any setup, and reading creates no key');
$apiKeyField = array_values(array_filter($rezdyRow['fields'], static fn($f) => $f['key'] === 'api_key'))[0];
check_settings($apiKeyField['type'] === 'secret' && $apiKeyField['configured'] === false && !array_key_exists('value', $apiKeyField), 'a secret field projects configured:false and no value key');
check_settings($rezdy->connectionState() === ['configured' => false, 'environment' => null, 'base_url' => null], 'Rezdy seam reports unconfigured');

// ── Save ─────────────────────────────────────────────────────────────────
$saved = $controller->saveConnection(new WP_REST_Request([
    'provider' => 'rezdy',
    'values'   => ['environment' => 'staging'],
    'secrets'  => ['api_key' => '  ' . SECRET . '  '],
]));
check_settings($saved->get_status() === 200 && $saved->get_data()['connection']['state'] === 'configured', 'saving environment + API key reads configured');
check_settings(!str_contains($encoded($saved), SECRET) && !str_contains($encoded($saved), 'rz-live'), 'the save response never contains the secret');
$savedKey = array_values(array_filter($saved->get_data()['connection']['fields'], static fn($f) => $f['key'] === 'api_key'))[0];
check_settings($savedKey['configured'] === true && !array_key_exists('value', $savedKey), 'the saved secret projects configured:true and no value');
check_settings(!str_contains($encoded($controller->listConnections(new WP_REST_Request())), SECRET), 'the list projection never contains the secret');
check_settings(($__settingsOptions[ConnectionStore::OPTION]['autoload'] ?? null) === 'no', 'the connection option is not autoloaded');
check_settings($list->get_data()['encryption'] === ['available' => true], 'the list reports that this server can seal secrets');

// ── The first save created the keyring ──────────────────────────────────
$ring = $__settingsOptions[CredentialKeyring::OPTION] ?? null;
$cipher = $keyring->cipher();
check_settings(is_array($ring) && $ring['autoload'] === 'no' && $ring['value']['active'] === $cipher->keyId() && count($ring['value']['keys']) === 1, 'the first secret save generated one data key, stored in a non-autoloaded option');
$wrapped = $ring['value']['keys'][$cipher->keyId()];
check_settings($wrapped['alg'] === 'xchacha20poly1305-ietf' && $wrapped['kid'] === CredentialCipher::idOf($wrapRaw), 'the data key is stored only sealed under the wrapping key');
check_settings(!str_contains(serialize($__settingsOptions), $wrapRaw) && !str_contains(serialize($__settingsOptions), base64_encode($wrapRaw)), 'the wrapping key is never stored');
check_settings(CredentialKeyring::derive('material') !== CredentialKeyring::derive('material' . "\0") && strlen(CredentialKeyring::derive('material')) === 32, 'the wrapping key is a 32-byte HKDF derivation of the site secret material');

// ── Encrypted at rest ────────────────────────────────────────────────────
$storedEnvelope = $__settingsOptions[ConnectionStore::OPTION]['value']['rezdy']['secrets']['api_key'];
check_settings(is_array($storedEnvelope) && $storedEnvelope['alg'] === 'xchacha20poly1305-ietf' && $storedEnvelope['kid'] === $cipher->keyId(), 'the stored secret is an XChaCha20-Poly1305 envelope under the current key id');
check_settings(!str_contains(serialize($__settingsOptions), SECRET) && !str_contains(serialize($__settingsOptions), 'rz-live'), 'no plaintext secret exists anywhere in stored options');
check_settings(strlen(base64_decode($storedEnvelope['nonce'])) === 24, 'each envelope carries a 24-byte random nonce');
$resealed = $cipher->seal(SECRET, CredentialCipher::context('rezdy', 'api_key'));
check_settings($resealed['nonce'] !== $storedEnvelope['nonce'] && $resealed['ct'] !== $storedEnvelope['ct'], 'sealing the same secret twice never produces the same ciphertext');
check_settings($cipher->open($storedEnvelope, CredentialCipher::context('rezdy', 'other_field')) === null, 'a sealed value moved to another provider/field slot does not open');
$tampered = $storedEnvelope;
$tampered['ct'] = base64_encode(substr(base64_decode($storedEnvelope['ct']), 0, -1) . 'x');
check_settings($cipher->open($tampered, CredentialCipher::context('rezdy', 'api_key')) === null, 'a tampered ciphertext does not open');
$otherRing = new CredentialKeyring([new CredentialCipher(random_bytes(32))]);
check_settings((new CredentialCipher(random_bytes(32)))->open($storedEnvelope, CredentialCipher::context('rezdy', 'api_key')) === null && (new ConnectorCredentials($store, $otherRing))->secret('rezdy', 'api_key') === null, 'a different key, or a different wrapping key, cannot read the secret');
check_settings((new SettingsConnectionsController($store, $otherRing, $guard))->listConnections(new WP_REST_Request())->get_data()['connections'][0]['state'] === 'incomplete', 'a secret the keyring cannot open does not count as configured');
check_settings($cipher->open(SECRET, CredentialCipher::context('rezdy', 'api_key')) === null, 'a plaintext (non-envelope) value is never accepted as a secret');

check_settings($credentials->secret('rezdy', 'api_key') === SECRET, 'ConnectorCredentials returns the trimmed secret server-side');
check_settings($rezdy->connectionState() === ['configured' => true, 'environment' => 'staging', 'base_url' => 'https://api.rezdy-staging.com/v1/'], 'Rezdy seam reports configured staging and its base URL, without the key');
check_settings(!str_contains(json_encode($rezdy->connectionState()), SECRET), 'the Rezdy connection state never carries the key');

// ── Empty secret leaves the stored value; clear removes it ───────────────
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'production'], 'secrets' => ['api_key' => '']]));
check_settings($credentials->secret('rezdy', 'api_key') === SECRET && $credentials->config('rezdy', 'environment') === 'production', 'an empty secret keeps the stored key while config updates');
$cleared = $controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'clear' => ['api_key']]));
check_settings($cleared->get_data()['connection']['state'] === 'incomplete' && $credentials->secret('rezdy', 'api_key') === null, 'clear removes the secret and the provider reads incomplete');

// ── Validation ───────────────────────────────────────────────────────────
check_settings($controller->saveConnection(new WP_REST_Request(['provider' => 'stripe']))->get_status() === 404, 'an unregistered provider is 404');
check_settings($controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['api_key' => 'x']]))->get_status() === 422, 'a secret sent as plain configuration is refused');
check_settings($controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['environment' => 'x']]))->get_status() === 422, 'configuration sent as a secret is refused');
check_settings($controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'moon']]))->get_status() === 422, 'a select value outside its options is refused');
check_settings($controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['unknown' => 'x']]))->get_status() === 422, 'an unknown field is refused');

// ── No wrapping key: fail closed, never plaintext ────────────────────────
$noKeyController = new SettingsConnectionsController($store, new CredentialKeyring([]), $guard);
check_settings($noKeyController->listConnections(new WP_REST_Request())->get_data()['encryption'] === ['available' => false], 'without a wrapping key the list reports secure storage unavailable');
$before = serialize($__settingsOptions);
check_settings($noKeyController->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => SECRET]]))->get_status() === 409, 'without a key a secret save is refused (409)');
check_settings(serialize($__settingsOptions) === $before, 'a refused secret save writes nothing');
check_settings($noKeyController->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'staging']]))->get_status() === 200, 'non-secret configuration still saves without a key');
check_settings(!(new CredentialCipher('too-short'))->isAvailable(), 'a key that is not 32 bytes is treated as no key');
check_settings(!CredentialKeyring::fromEnvironment()->isAvailable(), 'with no WordPress secret keys or QSD_CREDENTIAL_KEY, secure storage is unavailable');

// ── Disconnect ───────────────────────────────────────────────────────────
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => SECRET]]));
$gone = $controller->disconnect(new WP_REST_Request(['provider' => 'rezdy']));
check_settings($gone->get_data()['connection']['state'] === 'not_configured' && $credentials->secret('rezdy', 'api_key') === null, 'disconnect removes the stored configuration and secret');

// ── A refused save never creates the keyring ────────────────────────────
$__settingsOptions = [];
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => SECRET], 'clear' => ['environment']]));
check_settings(!isset($__settingsOptions[CredentialKeyring::OPTION]), 'a save refused for another field generates no data key');

// ── Wrapping key changes: fail closed, keep everything, allow re-entry ───
$__settingsOptions = [];
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'staging'], 'secrets' => ['api_key' => SECRET]]));
$sealedBefore = $__settingsOptions[ConnectionStore::OPTION]['value']['rezdy']['secrets']['api_key'];
$rotatedSite = new CredentialKeyring([new CredentialCipher(random_bytes(32))]);
$rotatedController = new SettingsConnectionsController($store, $rotatedSite, $guard);
$afterChange = $rotatedController->listConnections(new WP_REST_Request())->get_data();
check_settings($afterChange['encryption'] === ['available' => true] && $afterChange['connections'][0]['state'] === 'incomplete', 'after the WordPress secret keys change, storage stays available and the key reads as not set');
check_settings(!str_contains(json_encode($afterChange), SECRET), 'the unavailable key is never exposed');
$reentered = $rotatedController->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => 'rz-NEW-KEY-8c1f']]));
check_settings($reentered->get_status() === 200 && $reentered->get_data()['connection']['state'] === 'configured', 'an administrator re-enters the key through API Keys and it reads configured');
check_settings($rotatedSite->status() === ['generations' => 2, 'openable' => 1], 'the re-entry started a new data-key generation and kept the old sealed one');
check_settings($keyring->status() === ['generations' => 2, 'openable' => 1] && $keyring->cipher()->open($sealedBefore, CredentialCipher::context('rezdy', 'api_key')) === SECRET, 'nothing was deleted: the original wrapping key still opens the old generation');

// ── Save and rotation share one guard: both orderings ───────────────────
$__settingsOptions = [];
$wpdb->rows = [];
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'staging'], 'secrets' => ['api_key' => SECRET]]));
$rotation = new CredentialRotation($store, $keyring, $guard);

// Save first: a rotation that starts while the save holds the guard does nothing.
$duringSave = null;
$guard->hold(static function () use ($rotation, &$duringSave): void { $duringSave = $rotation->rotate(); });
check_settings($duringSave['ok'] === false && str_contains((string) $duringSave['error'], 'in progress') && $keyring->status()['generations'] === 1, 'a rotation that starts during a save changes nothing and reports failure');
check_settings(!isset($wpdb->rows[WpdbCredentialMutationGuard::ROW]), 'the guard is released after the save');

// Rotation first: a save that arrives mid-rotation is refused and writes nothing.
$sealedBefore = $__settingsOptions[ConnectionStore::OPTION]['value']['rezdy']['secrets']['api_key'];
$saveDuringRotation = null;
$racing = new CredentialRotation($store, $keyring, $guard, static function (string $phase) use ($controller, &$saveDuringRotation): void {
    if ($phase === 'planned') {
        $saveDuringRotation = $controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => 'rz-RACING-SAVE']]));
    }
});
$racingReport = $racing->rotate();
check_settings($saveDuringRotation->get_status() === 409 && !str_contains(json_encode($saveDuringRotation->get_data()), 'rz-RACING'), 'a save during a rotation is refused (409) and echoes nothing');
check_settings($racingReport['ok'] === true && $credentials->secret('rezdy', 'api_key') === SECRET && $keyring->status() === ['generations' => 1, 'openable' => 1], 'the rotation completes and the saved key still opens on the single new generation');
check_settings($__settingsOptions[ConnectionStore::OPTION]['value']['rezdy']['secrets']['api_key'] !== $sealedBefore, 'the stored key was re-sealed by the rotation, not overwritten by the refused save');
$after = $controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => 'rz-AFTER-ROTATION']]));
check_settings($after->get_status() === 200 && $credentials->secret('rezdy', 'api_key') === 'rz-AFTER-ROTATION', 'once the rotation finishes, the save goes through and stays readable');
$configDuringRotation = null;
(new CredentialRotation($store, $keyring, $guard, static function (string $phase) use ($controller, &$configDuringRotation): void {
    if ($phase === 'replaced') $configDuringRotation = $controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'production']]));
}))->rotate();
check_settings($configDuringRotation->get_status() === 409 && $credentials->config('rezdy', 'environment') === 'staging' && $credentials->secret('rezdy', 'api_key') === 'rz-AFTER-ROTATION', 'a configuration save cannot write back stale secret envelopes during a rotation');
$disconnectDuringRotation = null;
(new CredentialRotation($store, $keyring, $guard, static function (string $phase) use ($controller, &$disconnectDuringRotation): void {
    if ($phase === 'replaced') $disconnectDuringRotation = $controller->disconnect(new WP_REST_Request(['provider' => 'rezdy']));
}))->rotate();
check_settings($disconnectDuringRotation->get_status() === 409 && $credentials->secret('rezdy', 'api_key') === 'rz-AFTER-ROTATION', 'a disconnect during a rotation is refused and removes nothing');

// A lease left by a crashed request expires and is broken; a live one is not.
$now = 1_900_000_000;
$timed = new WpdbCredentialMutationGuard(static function () use (&$now): int { return $now; }, static function () use (&$now): void { $now++; }, 5);
$wpdb->rows[WpdbCredentialMutationGuard::ROW] = json_encode(['token' => 'crashed', 'expires_at' => $now - 1]);
check_settings($timed->hold(static fn(): string => 'ran') === 'ran' && !isset($wpdb->rows[WpdbCredentialMutationGuard::ROW]), 'an expired lease from a crashed request is broken and released');
$wpdb->rows[WpdbCredentialMutationGuard::ROW] = json_encode(['token' => 'live', 'expires_at' => $now + 60]);
$ran = false;
try { $timed->hold(static function () use (&$ran): void { $ran = true; }); $busy = false; } catch (CredentialMutationBusy) { $busy = true; }
check_settings($busy && !$ran && json_decode($wpdb->rows[WpdbCredentialMutationGuard::ROW], true)['token'] === 'live', 'a live lease times out the waiter, which runs nothing and leaves the holder in place');
unset($wpdb->rows[WpdbCredentialMutationGuard::ROW]);

// ── Server configuration sources (constants are process-wide, so last) ───
define('SECURE_AUTH_KEY', 'put your unique phrase here');
define('SECURE_AUTH_SALT', str_repeat('s', 64));
check_settings(!CredentialKeyring::fromEnvironment()->isAvailable(), 'the WordPress sample placeholder is never used as key material');
$legacyRaw = random_bytes(32);
define('QSD_CREDENTIAL_KEY', base64_encode($legacyRaw));
$envRing = CredentialKeyring::fromEnvironment();
$legacyEnvelope = (new CredentialCipher($legacyRaw))->seal(SECRET, CredentialCipher::context('rezdy', 'api_key'));
check_settings($envRing->isAvailable() && $envRing->cipher()->open($legacyEnvelope, CredentialCipher::context('rezdy', 'api_key')) === SECRET, 'an optional QSD_CREDENTIAL_KEY wraps the keyring, and a secret sealed directly under it by an earlier build still opens');

echo "All Settings Connections checks passed.\n";
