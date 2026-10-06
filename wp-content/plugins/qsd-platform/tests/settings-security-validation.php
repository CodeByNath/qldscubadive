<?php

declare(strict_types=1);

// Runs the real BrokerValidation, CredentialBroker, WpdbRequestKeyStore,
// BrokerAuditLog, CredentialRotation, RezdyConnectionCheck and the Security
// validation route against an in-memory options table and a recording HTTP
// transport. Proves the Phase 2 runtime check deterministically: server-derived
// identity, the allow-listed caller, the full request-key lifecycle with one
// provider call, the staging-only Rezdy bound, and no secret, key, hash or key
// id in any report or audit entry. The real-runtime run happens on staging2.

$__options = [];
$__routes = [];
const ADMINISTRATOR = ['manage_qsd', 'manage_options'];
const PLATFORM_MANAGER = ['manage_qsd'];
$__caps = ADMINISTRATOR;
$__userId = 5;

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
function add_action(string $hook, callable $callback): void {}
function register_rest_route(string $namespace, string $route, array $args): bool {
    global $__routes;
    $__routes[$route] = $args;
    return true;
}
function current_user_can(string $cap): bool { global $__caps; return in_array($cap, $__caps, true); }
function get_current_user_id(): int { global $__userId; return $__userId; }
function rest_ensure_response(mixed $value): WP_REST_Response {
    return $value instanceof WP_REST_Response ? $value : new WP_REST_Response($value, 200);
}
const ARRAY_A = 'ARRAY_A';

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

/** The options table rows the request-key store uses. */
final class FakeWpdb
{
    public string $options = 'wp_options';
    /** @var array<string, array{option_value: string, autoload: string}> */
    public array $rows = [];

    public function insert(string $table, array $data): int|false
    {
        if (isset($this->rows[$data['option_name']])) return false;
        $this->rows[$data['option_name']] = ['option_value' => $data['option_value'], 'autoload' => $data['autoload']];
        return 1;
    }
    public function prepare(string $query, mixed ...$args): array { return [$query, $args]; }
    public function get_var(array $prepared): ?string { return $this->rows[$prepared[1][0]]['option_value'] ?? null; }
    public function get_results(array $prepared, string $output): array
    {
        $prefix = rtrim($prepared[1][0], '%');
        $out = [];
        foreach ($this->rows as $name => $row) {
            if (str_starts_with($name, $prefix)) $out[] = ['option_name' => $name, 'option_value' => $row['option_value']];
        }
        return $out;
    }
    public function esc_like(string $text): string { return $text; }
    public function delete(string $table, array $where): int
    {
        if (!isset($this->rows[$where['option_name']])) return 0;
        unset($this->rows[$where['option_name']]);
        return 1;
    }
}
$wpdb = new FakeWpdb();

require_once __DIR__ . '/autoload.php';

use QSD\Platform\Modules\Settings\Connections\ConnectionProviders;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnectionCheck;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;
use QSD\Platform\Modules\Settings\Http\SettingsSecurityController;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\BrokerValidation;
use QSD\Platform\Modules\Settings\Security\CredentialBroker;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialKeyring;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;
use QSD\Platform\Modules\Settings\Security\ProviderSecrets;
use QSD\Platform\Modules\Settings\Security\WpdbRequestKeyStore;
use QSD\Platform\Modules\Settings\SettingsModule;

function check_validation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "  ok — {$message}\n";
}

const API_KEY = 'rz-stg-SECRET-5e1d9a';

$wrapRaw = random_bytes(32);
$keyring = new CredentialKeyring([new CredentialCipher($wrapRaw)]);
$cipher = $keyring->sealingCipher();
$store = new ConnectionStore();
$credentials = new ConnectorCredentials($store, $keyring);

/** Records every request; answers with the queued status and body. */
final class RecordingTransport
{
    /** @var list<array{url: string, args: array<string, mixed>}> */
    public array $calls = [];
    public ?int $status = 200;
    public ?string $body = '{"requestStatus":{"success":true,"version":"v1"},"products":[{"productCode":"P1","name":"Reef dive"}]}';

    public function __invoke(string $url, array $args): array
    {
        $this->calls[] = ['url' => $url, 'args' => $args];
        return ['status' => $this->status, 'body' => $this->body];
    }
}

$configure = static function (string $environment) use ($store, $cipher): void {
    $store->write('rezdy', ['config' => ['environment' => $environment], 'secrets' => [
        'api_key' => $cipher->seal(API_KEY, CredentialCipher::context('rezdy', 'api_key')),
    ]]);
};
$secrets = new ProviderSecrets('rezdy', static fn(string $field): ?string => $field === 'api_key' ? API_KEY : null);

echo "Settings security validation\n";

// ── 1. Rezdy connection check ───────────────────────────────────────────
$transport = new RecordingTransport();
$check = new RezdyConnectionCheck($credentials, Closure::fromCallable($transport));
$configure('staging');
$result = $check->perform(RezdyConnector::SCOPE_VERIFY, $secrets, []);
check_validation($result['outcome'] === RezdyConnectionCheck::AUTHENTICATED && $result['http_status'] === 200 && $result['environment'] === 'staging', 'an accepted key on the staging API classifies as authenticated');
check_validation(count($transport->calls) === 1 && str_starts_with($transport->calls[0]['url'], 'https://api.rezdy-staging.com/v1/products?'), 'the check makes one read-only request to the Rezdy staging API');
check_validation($transport->calls[0]['args']['redirection'] === 0 && $transport->calls[0]['args']['timeout'] === 10, 'the request follows no redirect and times out');
check_validation(array_keys($result) === ['provider', 'environment', 'outcome', 'http_status', 'latency_ms'], 'the result is outcome metadata only');
check_validation(!str_contains(json_encode($result), API_KEY) && !str_contains(json_encode($result), 'Reef dive') && !str_contains(json_encode($result), 'apiKey'), 'the result carries no key, no URL and no response body');

$cases = [
    [401, '{"requestStatus":{"success":false}}', RezdyConnectionCheck::UNAUTHORIZED, 'HTTP 401'],
    [200, '{"requestStatus":{"success":false,"error":{"errorCode":"401","errorMessage":"Invalid apiKey ' . API_KEY . '"}}}', RezdyConnectionCheck::UNAUTHORIZED, 'a Rezdy 401 error code'],
    [429, '', RezdyConnectionCheck::RATE_LIMITED, 'HTTP 429'],
    [503, 'oops', RezdyConnectionCheck::UPSTREAM_ERROR, 'HTTP 503'],
    [200, '<html>', RezdyConnectionCheck::UNEXPECTED_RESPONSE, 'a non-JSON 200'],
    [null, null, RezdyConnectionCheck::NETWORK_ERROR, 'a transport failure'],
];
foreach ($cases as [$status, $body, $outcome, $label]) {
    $transport->status = $status;
    $transport->body = $body;
    $classified = $check->perform(RezdyConnector::SCOPE_VERIFY, $secrets, []);
    check_validation($classified['outcome'] === $outcome && !str_contains(json_encode($classified), API_KEY), "{$label} classifies as {$outcome} without echoing upstream text");
}

$transport->calls = [];
$configure('production');
check_validation($check->perform(RezdyConnector::SCOPE_VERIFY, $secrets, [])['outcome'] === RezdyConnectionCheck::REFUSED_ENVIRONMENT && $transport->calls === [], 'the production environment is refused without any request (Phase 2 bound)');
$configure('staging');
$none = new ProviderSecrets('rezdy', static fn(string $field): ?string => null);
check_validation($check->perform(RezdyConnector::SCOPE_VERIFY, $none, [])['outcome'] === RezdyConnectionCheck::NOT_CONFIGURED && $transport->calls === [], 'no API key means no request');
$threw = false;
try { $check->perform('catalogue.read', $secrets, []); } catch (InvalidArgumentException) { $threw = true; }
check_validation($threw && $transport->calls === [], 'any other scope is refused without a request');

// ── 2. The validation run ───────────────────────────────────────────────
$now = 1_800_000_000;
$clock = static function () use (&$now): int { return $now; };
$sleep = static function (int $seconds) use (&$now): void { $now += $seconds; };
$build = static function (?CredentialCipher $previous = null) use ($store, $keyring, $credentials, $clock, $sleep, &$transport): BrokerValidation {
    $transport = new RecordingTransport();
    $broker = new CredentialBroker(ConnectionProviders::all(), $credentials, new WpdbRequestKeyStore(), new BrokerAuditLog(),
        ['rezdy' => new RezdyConnectionCheck($credentials, Closure::fromCallable($transport))], $clock, SettingsModule::brokerCallers());
    return new BrokerValidation($broker, new WpdbRequestKeyStore(), new BrokerAuditLog(), $store, $keyring,
        new CredentialRotation($store, $previous ?? new CredentialCipher(null), $keyring->cipher()), 'rezdy', RezdyConnector::SCOPE_VERIFY, $sleep);
};

check_validation(SettingsModule::brokerCallers() === [BrokerValidation::CALLER => ['rezdy:connection.verify']], 'the only allow-listed caller is the validation run, for Rezdy connection.verify only');

$configure('staging');
$report = $build()->run(5);
$failed = array_values(array_filter($report['checks'], static fn($c) => !$c['ok']));
check_validation($report['passed'] === true && $failed === [], 'a configured install passes every check');
check_validation($report['identity'] === ['user_id' => 5, 'caller' => BrokerValidation::CALLER, 'source' => 'server'], 'the report names the server-derived user and the allow-listed caller');
check_validation(count($transport->calls) === 1, 'the whole run makes exactly one provider call');
check_validation($report['provider_check']['outcome'] === RezdyConnectionCheck::AUTHENTICATED, 'the provider result comes back through the broker');
check_validation($wpdb->rows === [], 'no request-key row is left in the store');
$names = array_column($report['checks'], 'check');
foreach (['a replayed key is refused', 'an expired key is refused', 'a key presented with the wrong binding is refused', 'a binding mismatch burns the key', 'issuing sweeps an expired, never-presented key from the store'] as $expected) {
    check_validation(in_array($expected, $names, true), "the run covers: {$expected}");
}
$events = array_values(array_filter($report['checks'], static fn($c) => $c['check'] === "the audit records this run's outcomes"))[0]['detail']['events'];
check_validation($events === ['expired' => 1, 'issued' => 4, 'rejected_binding' => 1, 'rejected_expired' => 1, 'rejected_unknown' => 2, 'used' => 1], 'the audit records every lifecycle outcome of the run');
$text = json_encode($report) . serialize($report) . serialize($__options[BrokerAuditLog::OPTION]);
check_validation(!str_contains($text, API_KEY) && !str_contains($text, 'qrk_') && !str_contains($text, (string) $cipher->keyId()) && !str_contains($text, base64_encode($wrapRaw)), 'the report and audit hold no secret, request key or key id');
check_validation(preg_match('/[0-9a-f]{64}/', json_encode($report)) === 0, 'the report holds no key hash');

// Server-derived identity: a different session user is bound, whatever the client sends.
$report7 = $build()->run(7);
check_validation($report7['identity']['user_id'] === 7 && $report7['passed'], 'the bound user follows the session');
$noUser = $build()->run(0);
check_validation($noUser['passed'] === false && $noUser['provider_check'] === null && $transport->calls === [], 'with no authenticated user, no key is issued and no provider call is made');

// Fail closed: not configured, and secrets sealed under a previous key.
$store->remove('rezdy');
$unconfigured = $build()->run(5);
$issue = array_values(array_filter($unconfigured['checks'], static fn($c) => $c['check'] === 'a request key is issued for the allow-listed caller'))[0];
check_validation($unconfigured['passed'] === false && $issue['detail']['reason'] === 'not_configured' && $transport->calls === [], 'an unconfigured provider fails the run before any provider call');

$oldKey = new CredentialCipher(random_bytes(32));
$store->write('rezdy', ['config' => ['environment' => 'staging'], 'secrets' => ['api_key' => $oldKey->seal(API_KEY, CredentialCipher::context('rezdy', 'api_key'))]]);
$stale = $build($oldKey)->run(5);
check_validation($stale['passed'] === false && $stale['rotation']['previous'] === ['rezdy:api_key'] && $stale['rotation']['previous_key_defined'] === true, 'a secret still under the previous key is reported as awaiting re-seal');
check_validation($stale['provider_check'] === null && $transport->calls === [], 'until it is re-sealed the credential reads as not configured and no call is made');
$reseal = (new CredentialRotation($store, $oldKey, $cipher))->reseal();
$after = $build()->run(5);
check_validation($reseal['ok'] && $after['passed'] && $after['rotation']['current'] === ['rezdy:api_key'], 'after the re-seal the run passes under the new key');

// ── 3. Route ────────────────────────────────────────────────────────────
$controller = new SettingsSecurityController(static fn(): BrokerValidation => $build());
$controller->registerRoutes();
$route = $__routes['/admin/settings/security/broker-validation'] ?? null;
check_validation($route !== null && $route['methods'] === 'POST', 'the validation route is registered as POST');
$__caps = PLATFORM_MANAGER;
check_validation($controller->requireAuthority() === false, 'a platform manager without administrator authority is refused');
$__caps = ['manage_options'];
check_validation($controller->requireAuthority() === false, 'administrator authority without the platform capability is refused');
$__caps = ADMINISTRATOR;
check_validation($controller->requireAuthority() === true, 'an administrator may run it');
$__userId = 9;
$response = $controller->runValidation(new WP_REST_Request(['user_id' => 1, 'caller' => 'rogue-component', 'provider' => 'stripe', 'subject' => 'QSDS1']));
$data = $response->get_data();
check_validation($data['success'] === true && $data['validation']['identity'] === ['user_id' => 9, 'caller' => BrokerValidation::CALLER, 'source' => 'server'], 'client-supplied user, caller, provider and subject are ignored');
check_validation(!str_contains(json_encode($data), API_KEY) && !str_contains(json_encode($data), 'qrk_'), 'the REST response carries no secret or request key');

echo "All Settings security validation checks passed.\n";
