<?php

declare(strict_types=1);

// Runs the real Settings Connections controller, store, credential capability
// and Rezdy connector seam against an in-memory option boundary. Proves the
// secret contract: a secret enters on save, is readable server-side through
// ConnectorCredentials only, and never appears in any REST projection.

$__settingsOptions = [];
$__routes = [];
$__canManage = true;

function sanitize_text_field(mixed $value): string { return trim(strip_tags((string) $value)); }
function add_action(string $hook, callable $callback): void {}
function register_rest_route(string $namespace, string $route, array $args): bool {
    global $__routes;
    $__routes[$route] = $args;
    return true;
}
function current_user_can(string $cap): bool { global $__canManage; return $__canManage && $cap === 'manage_qsd'; }
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

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;

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
$cipher = new CredentialCipher(random_bytes(32));
$controller = new SettingsConnectionsController($store, $cipher);
$controller->registerRoutes();
$credentials = new ConnectorCredentials($store, $cipher);
$rezdy = new RezdyConnector($credentials);

$encoded = static fn(WP_REST_Response $response): string => json_encode($response->get_data());

echo "Settings Connections\n";

check_settings(isset($__routes['/admin/settings/connections']) && isset($__routes['/admin/settings/connections/(?P<provider>[a-z0-9-]+)']), 'list and per-provider routes are registered');
foreach ($__routes as $route => $args) {
    $endpoints = isset($args['methods']) ? [$args] : $args;
    foreach ($endpoints as $endpoint) {
        check_settings(($endpoint['permission_callback'] ?? null) === [$controller, 'requireAdmin'], "{$route} {$endpoint['methods']} is gated by requireAdmin");
    }
}
check_settings($controller->requireAdmin() === true, 'a manage_qsd user passes the gate');
$__canManage = false;
check_settings($controller->requireAdmin() === false, 'a user without manage_qsd is refused');
$__canManage = true;

$list = $controller->listConnections(new WP_REST_Request());
$rezdyRow = $list->get_data()['connections'][0] ?? [];
check_settings(count($list->get_data()['connections']) === 1 && $rezdyRow['provider'] === 'rezdy', 'Rezdy is the one registered provider');
check_settings($rezdyRow['state'] === 'not_configured' && $rezdyRow['updated_at'] === null, 'an unsaved provider reads not_configured');
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
$otherKey = new CredentialCipher(random_bytes(32));
check_settings($otherKey->open($storedEnvelope, CredentialCipher::context('rezdy', 'api_key')) === null && (new ConnectorCredentials($store, $otherKey))->secret('rezdy', 'api_key') === null, 'a different server key cannot read the secret');
check_settings((new SettingsConnectionsController($store, $otherKey))->listConnections(new WP_REST_Request())->get_data()['connections'][0]['state'] === 'incomplete', 'a secret sealed under another key does not count as configured');
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

// ── No key: fail closed, never plaintext ─────────────────────────────────
$noKey = new CredentialCipher(null);
$noKeyController = new SettingsConnectionsController($store, $noKey);
check_settings($noKeyController->listConnections(new WP_REST_Request())->get_data()['encryption'] === ['available' => false], 'without QSD_CREDENTIAL_KEY the list reports encryption unavailable');
$before = serialize($__settingsOptions);
check_settings($noKeyController->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => SECRET]]))->get_status() === 409, 'without a key a secret save is refused (409)');
check_settings(serialize($__settingsOptions) === $before, 'a refused secret save writes nothing');
check_settings($noKeyController->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'values' => ['environment' => 'staging']]))->get_status() === 200, 'non-secret configuration still saves without a key');
check_settings(!(new CredentialCipher('too-short'))->isAvailable(), 'a key that is not 32 bytes is treated as no key');
check_settings(!CredentialCipher::fromEnvironment()->isAvailable(), 'no QSD_CREDENTIAL_KEY constant means encryption is unavailable');

// ── Disconnect ───────────────────────────────────────────────────────────
$controller->saveConnection(new WP_REST_Request(['provider' => 'rezdy', 'secrets' => ['api_key' => SECRET]]));
$gone = $controller->disconnect(new WP_REST_Request(['provider' => 'rezdy']));
check_settings($gone->get_data()['connection']['state'] === 'not_configured' && $credentials->secret('rezdy', 'api_key') === null, 'disconnect removes the stored configuration and secret');

echo "All Settings Connections checks passed.\n";
