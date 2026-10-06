<?php

declare(strict_types=1);

// Runs the real CredentialBroker, WpdbRequestKeyStore, BrokerAuditLog,
// CredentialCipher and ConnectorCredentials against an in-memory options
// table (a minimal $wpdb) and option API. Proves the request-key contract:
// random bound keys, hash-only storage, TTL, atomic single use, replay and
// binding rejection, safe audit, and that a consumer never receives a secret.

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
const ARRAY_A = 'ARRAY_A';

/** The options table, row-addressed, with the $wpdb calls the store uses. */
final class FakeWpdb
{
    public string $options = 'wp_options';
    /** @var array<string, array{option_value: string, autoload: string}> */
    public array $rows = [];

    public function insert(string $table, array $data): int|false
    {
        if (isset($this->rows[$data['option_name']])) return false; // unique key
        $this->rows[$data['option_name']] = ['option_value' => $data['option_value'], 'autoload' => $data['autoload']];
        return 1;
    }
    public function prepare(string $query, mixed ...$args): array { return [$query, $args]; }
    public function get_var(array $prepared): ?string
    {
        return $this->rows[$prepared[1][0]]['option_value'] ?? null;
    }
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

use QSD\Platform\Modules\Settings\Connections\ConnectionProviderDefinition;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\BrokeredAccess;
use QSD\Platform\Modules\Settings\Security\BrokeredProviderOperation;
use QSD\Platform\Modules\Settings\Security\BrokerGrant;
use QSD\Platform\Modules\Settings\Security\BrokerRejected;
use QSD\Platform\Modules\Settings\Security\CredentialBroker;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialKeyring;
use QSD\Platform\Modules\Settings\Security\ProviderSecrets;
use QSD\Platform\Modules\Settings\Security\RequestKeyStore;
use QSD\Platform\Modules\Settings\Security\WpdbRequestKeyStore;

$checks = 0;
function check_broker(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function rejected(callable $fn): ?string
{
    try {
        $fn();
    } catch (BrokerRejected $e) {
        return $e->reason;
    }
    return null;
}

const SECRET = 'tp-live-SECRET-c0ffee';

/** A Settings-owned provider operation double: sees the secret, returns safe data. */
final class RecordingOperation implements BrokeredProviderOperation
{
    public ?string $sawSecret = null;
    public string $mode = 'safe';
    public function perform(string $scope, ProviderSecrets $secrets, array $params): array
    {
        $this->sawSecret = $secrets->get('api_key');
        return match ($this->mode) {
            'leak'  => ['token' => 'Bearer ' . $this->sawSecret],
            'throw' => throw new RuntimeException('upstream said ' . $this->sawSecret),
            default => ['scope' => $scope, 'items' => 3, 'subject' => $params['subject'] ?? null],
        };
    }
}

$testPay = new ConnectionProviderDefinition('testpay', 'TestPay', 'Broker test provider.', [
    ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true],
], ['catalogue.read']);
$unconfigured = new ConnectionProviderDefinition('idle', 'Idle', 'Never configured.', [
    ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true],
], ['catalogue.read']);

$keyring = new CredentialKeyring([new CredentialCipher(random_bytes(32))]);
$cipher = $keyring->sealingCipher();
$store  = new ConnectionStore();
$store->write('testpay', ['config' => [], 'secrets' => ['api_key' => $cipher->seal(SECRET, CredentialCipher::context('testpay', 'api_key'))]]);
$credentials = new ConnectorCredentials($store, $keyring);

$now = 1_800_000_000;
$clock = static function () use (&$now): int { return $now; };
$operation = new RecordingOperation();
$keys  = new WpdbRequestKeyStore();
$audit = new BrokerAuditLog();
$providers = ['testpay' => $testPay, 'idle' => $unconfigured, 'rezdy' => RezdyConnector::definition()];
$callers = ['service-import' => ['testpay:catalogue.read', 'idle:catalogue.read'], 'booking-sync' => ['testpay:catalogue.write']];
$broker = new CredentialBroker($providers, $credentials, $keys, $audit, ['testpay' => $operation], $clock, $callers);

$grant = new BrokerGrant('testpay', 'catalogue.read', 'service-import', 7, 'QSDS2A7KZ');

echo "Credential broker\n";

// ── Issuance ─────────────────────────────────────────────────────────────
$issued = $broker->issue($grant);
check_broker(preg_match('/^qrk_[A-Za-z0-9_-]{43}$/', $issued->key) === 1, 'a request key is qrk_ + 32 random bytes base64url');
check_broker(preg_match('/^req_[0-9a-f]{16}$/', $issued->requestId) === 1, 'the audit handle is a separate req_ id');
check_broker(!str_starts_with($issued->key, 'QSD') && !str_starts_with($issued->requestId, 'QSD'), 'neither the key nor the request id is a Platform ID');
check_broker($issued->expiresAt === $now + CredentialBroker::DEFAULT_TTL, 'the default TTL is 60 seconds');
check_broker($broker->issue($grant)->key !== $issued->key, 'two issuances never share a key');
$rowName = WpdbRequestKeyStore::PREFIX . hash('sha256', $issued->key);
check_broker(isset($wpdb->rows[$rowName]) && $wpdb->rows[$rowName]['autoload'] === 'no', 'the record is stored under the SHA-256 of the key, not autoloaded');
$table = json_encode($wpdb->rows);
check_broker(!str_contains($table, $issued->key) && !str_contains($table, substr($issued->key, 4)), 'the key itself is never stored');
check_broker(!str_contains($table, SECRET) && !str_contains(serialize($__options[BrokerAuditLog::OPTION]), SECRET), 'issuance stores and audits no provider secret');
$record = json_decode($wpdb->rows[$rowName]['option_value'], true);
check_broker($record['provider'] === 'testpay' && $record['scope'] === 'catalogue.read' && $record['caller'] === 'service-import' && $record['user_id'] === 7 && $record['subject'] === 'QSDS2A7KZ', 'the record binds provider, scope, caller, user and subject');
check_broker(str_contains(print_r($issued, true), '[redacted]') && !str_contains(print_r($issued, true), $issued->key), 'the issued key is redacted from debug output');
check_broker($broker->issue($grant, 100_000)->expiresAt === $now + CredentialBroker::MAX_TTL, 'TTL is clamped to the 300-second maximum');

check_broker(rejected(fn() => $broker->issue(new BrokerGrant('testpay', 'payments.refund', 'service-import', 7))) === BrokerRejected::UNDECLARED_SCOPE, 'a scope the provider does not declare is refused');
check_broker(rejected(fn() => $broker->issue(new BrokerGrant('nobody', 'catalogue.read', 'service-import', 7))) === BrokerRejected::UNKNOWN_PROVIDER, 'an unknown provider is refused');
check_broker(rejected(fn() => $broker->issue(new BrokerGrant('idle', 'catalogue.read', 'service-import', 7))) === BrokerRejected::NOT_CONFIGURED, 'an unconfigured provider cannot be brokered');
check_broker(rejected(fn() => $broker->issue(new BrokerGrant('rezdy', 'catalogue.read', 'service-import', 7))) === BrokerRejected::UNDECLARED_SCOPE, 'Rezdy declares no importer scope until the Owner-reviewed importer exists');
check_broker(RezdyConnector::definition()->scopes === [RezdyConnector::SCOPE_VERIFY], 'Rezdy declares only the read-only connection.verify scope');
check_broker(rejected(fn() => $broker->issue(new BrokerGrant('testpay', 'catalogue.read', 'rogue-component', 7))) === BrokerRejected::UNKNOWN_CALLER, 'a caller missing from the server-side allow-list is refused');
check_broker(rejected(fn() => $broker->issue(new BrokerGrant('testpay', 'catalogue.read', 'booking-sync', 7))) === BrokerRejected::UNKNOWN_CALLER, 'an allow-listed caller is refused a provider scope it is not listed for');
check_broker(rejected(fn() => (new CredentialBroker($providers, $credentials, $keys, $audit, ['testpay' => $operation], $clock))->issue($grant)) === BrokerRejected::UNKNOWN_CALLER, 'with no allow-list, no caller gets a key');
check_broker(rejected(fn() => new BrokerGrant('testpay', 'catalogue.read', 'Bad Caller!', 7)) === BrokerRejected::INVALID_REQUEST, 'a malformed caller name is refused');
check_broker(rejected(fn() => new BrokerGrant('testpay', '*', 'service-import', 7)) === BrokerRejected::INVALID_REQUEST, 'a wildcard scope is refused');

// ── Consume once, perform server-side ────────────────────────────────────
$result = $broker->perform($issued->key, $grant, ['subject' => 'QSDS2A7KZ']);
check_broker($result === ['scope' => 'catalogue.read', 'items' => 3, 'subject' => 'QSDS2A7KZ'], 'a valid key performs the provider operation and returns its result');
check_broker($operation->sawSecret === SECRET, 'the Settings-owned operation received the decrypted credential server-side');
check_broker(!str_contains(json_encode($result), SECRET), 'the consumer result carries no secret');
check_broker(!isset($wpdb->rows[$rowName]), 'consumption removes the record');

check_broker(rejected(fn() => $broker->perform($issued->key, $grant)) === BrokerRejected::UNKNOWN_KEY, 'replaying a consumed key is refused');
check_broker(rejected(fn() => $broker->perform('qrk_' . str_repeat('A', 43), $grant)) === BrokerRejected::UNKNOWN_KEY, 'a forged key is refused');

// Store-level atomicity and a racing read: two presenters both read the record
// before either claims it — only one claim can succeed.
$raceKey = $broker->issue($grant)->key;
$raceHash = hash('sha256', $raceKey);
$snapshot = $keys->find($raceHash);
final class StaleReadStore implements RequestKeyStore
{
    public function __construct(private RequestKeyStore $inner, private array $snapshot) {}
    public function put(string $hash, array $record): void { $this->inner->put($hash, $record); }
    public function find(string $hash): ?array { return $this->snapshot; }
    public function claim(string $hash): bool { return $this->inner->claim($hash); }
    public function sweepExpired(int $now): array { return $this->inner->sweepExpired($now); }
}
$racer = new CredentialBroker($providers, $credentials, new StaleReadStore($keys, $snapshot), $audit, ['testpay' => $operation], $clock, $callers);
check_broker($broker->perform($raceKey, $grant) !== [], 'the first of two racing presenters wins');
check_broker(rejected(fn() => $racer->perform($raceKey, $grant)) === BrokerRejected::REPLAYED, 'the second racing presenter, holding a stale read, loses the atomic claim');
$claimKey = $broker->issue($grant)->key;
check_broker($keys->claim(hash('sha256', $claimKey)) === true && $keys->claim(hash('sha256', $claimKey)) === false, 'the row claim succeeds exactly once');

// ── TTL ──────────────────────────────────────────────────────────────────
$short = $broker->issue($grant, 30);
$now += 31;
check_broker(rejected(fn() => $broker->perform($short->key, $grant)) === BrokerRejected::EXPIRED, 'an expired key is refused');
check_broker(rejected(fn() => $broker->perform($short->key, $grant)) === BrokerRejected::UNKNOWN_KEY, 'an expired key is burned, not left retryable');
$edge = $broker->issue($grant, 30);
$now += 30;
check_broker($broker->perform($edge->key, $grant) !== [], 'a key is still valid at exactly its expiry second');

// ── Binding ──────────────────────────────────────────────────────────────
$mismatches = [
    'caller'   => new BrokerGrant('testpay', 'catalogue.read', 'booking-sync', 7, 'QSDS2A7KZ'),
    'user'     => new BrokerGrant('testpay', 'catalogue.read', 'service-import', 8, 'QSDS2A7KZ'),
    'scope'    => new BrokerGrant('testpay', 'catalogue.write', 'service-import', 7, 'QSDS2A7KZ'),
    'provider' => new BrokerGrant('idle', 'catalogue.read', 'service-import', 7, 'QSDS2A7KZ'),
    'subject'  => new BrokerGrant('testpay', 'catalogue.read', 'service-import', 7, 'QSDS9Z9Z9'),
];
foreach ($mismatches as $what => $wrong) {
    $operation->sawSecret = null;
    $key = $broker->issue($grant)->key;
    check_broker(rejected(fn() => $broker->perform($key, $wrong)) === BrokerRejected::BINDING_MISMATCH, "a key presented with the wrong {$what} is refused");
    check_broker($operation->sawSecret === null, "a wrong-{$what} presentation never reaches the credential");
    check_broker(rejected(fn() => $broker->perform($key, $grant)) === BrokerRejected::UNKNOWN_KEY, "a wrong-{$what} presentation burns the key");
}

// ── A secret never reaches the consumer ──────────────────────────────────
$operation->mode = 'leak';
$key = $broker->issue($grant)->key;
check_broker(rejected(fn() => $broker->perform($key, $grant)) === BrokerRejected::SECRET_IN_RESULT, 'a result containing the credential is withheld');
$operation->mode = 'throw';
$key = $broker->issue($grant)->key;
try {
    $broker->perform($key, $grant);
    check_broker(false, 'a failing operation must reject');
} catch (BrokerRejected $e) {
    check_broker($e->reason === BrokerRejected::OPERATION_FAILED && !str_contains($e->getMessage(), SECRET), 'a failing operation reports a generic failure without the upstream text');
}
$operation->mode = 'safe';
$noOp = new CredentialBroker($providers, $credentials, $keys, $audit, [], $clock, $callers);
$key = $noOp->issue($grant)->key;
check_broker(rejected(fn() => $noOp->perform($key, $grant)) === BrokerRejected::NO_OPERATION, 'a provider with no Settings-owned operation cannot be performed');

$handle = new ProviderSecrets('testpay', static fn(string $field): ?string => SECRET);
check_broker(!str_contains(print_r($handle, true), SECRET), 'the credential handle hides its contents from debug output');
$serialised = false;
try { serialize($handle); } catch (LogicException) { $serialised = true; }
check_broker($serialised, 'the credential handle refuses serialisation');

// ── Sweep and audit ──────────────────────────────────────────────────────
$stale = $broker->issue($grant, 5);
$now += 6;
$broker->issue($grant);
check_broker(!isset($wpdb->rows[WpdbRequestKeyStore::PREFIX . hash('sha256', $stale->key)]), 'issuance sweeps expired, never-presented records');
$events = array_column($audit->entries(), 'event');
foreach ([BrokerAuditLog::ISSUED, BrokerAuditLog::USED, BrokerAuditLog::EXPIRED, BrokerAuditLog::REJECTED_UNKNOWN, BrokerAuditLog::REJECTED_REPLAY, BrokerAuditLog::REJECTED_EXPIRED, BrokerAuditLog::REJECTED_BINDING, BrokerAuditLog::FAILED] as $event) {
    check_broker(in_array($event, $events, true), "the audit records {$event}");
}
$auditText = serialize($audit->entries());
check_broker(!str_contains($auditText, SECRET) && !str_contains($auditText, 'qrk_') && !str_contains($auditText, hash('sha256', $issued->key)), 'the audit never contains a key, a key hash, or a secret');
$used = array_values(array_filter($audit->entries(), static fn($e) => $e['event'] === 'used'))[0];
check_broker(array_keys($used) === ['event', 'at', 'request_id', 'provider', 'scope', 'caller', 'user_id', 'subject'], 'an audit entry is exactly event, time, request id, provider, scope, caller, user and subject');
for ($i = 0; $i < BrokerAuditLog::MAX + 5; $i++) {
    $audit->record(BrokerAuditLog::ISSUED, ['request_id' => "req_{$i}"], $now);
}
check_broker(count($audit->entries()) === BrokerAuditLog::MAX, 'the audit is bounded to its newest 200 entries');

// ── Tool access: brokered authority only ────────────────────────────────
$sessionUser = 11;
$operation->mode = 'safe';
$tool = new BrokeredAccess($broker, 'service-import', static function () use (&$sessionUser): int { return $sessionUser; });
$toolResult = $tool->run('testpay', 'catalogue.read', ['subject' => 'QSDS2A7KZ'], 'QSDS2A7KZ');
check_broker($toolResult === ['scope' => 'catalogue.read', 'items' => 3, 'subject' => 'QSDS2A7KZ'] && $operation->sawSecret === SECRET, 'a Tool gets the operation result; only the Settings-owned operation saw the credential');
$toolEvents = array_slice($audit->entries(), -2); // the audit is bounded and already full here
check_broker(array_column($toolEvents, 'event') === [BrokerAuditLog::ISSUED, BrokerAuditLog::USED] && $toolEvents[0]['caller'] === 'service-import' && $toolEvents[0]['user_id'] === 11, 'each Tool call issues and consumes one key bound to its caller and the session user');
check_broker(array_filter(array_keys($wpdb->rows), static fn(string $name) => str_starts_with($name, WpdbRequestKeyStore::PREFIX) && json_decode($wpdb->rows[$name]['option_value'], true)['user_id'] === 11) === [], 'no Tool request key is left usable');
$operation->mode = 'leak';
check_broker(rejected(fn() => $tool->run('testpay', 'catalogue.read')) === BrokerRejected::SECRET_IN_RESULT, 'a result carrying the credential is withheld from the Tool');
$operation->mode = 'safe';
check_broker(rejected(fn() => $tool->run('testpay', 'catalogue.write')) === BrokerRejected::UNDECLARED_SCOPE && rejected(fn() => (new BrokeredAccess($broker, 'booking-sync', static fn(): int => 11))->run('testpay', 'catalogue.read')) === BrokerRejected::UNKNOWN_CALLER, 'a Tool is held to its own caller allow-list and the declared scopes');
$sessionUser = 0;
check_broker(rejected(fn() => $tool->run('testpay', 'catalogue.read')) === BrokerRejected::UNAUTHENTICATED, 'with no session user a Tool gets nothing');
$exposed = print_r($tool, true);
try { serialize($tool); $serialised = true; } catch (LogicException) { $serialised = false; }
check_broker(!str_contains($exposed, SECRET) && !str_contains($exposed, 'qrk_') && !$serialised, 'a Tool\'s access object exposes no credential or key and cannot be serialised');
$toolApi = array_map(static fn(ReflectionMethod $m) => $m->getName(), (new ReflectionClass(BrokeredAccess::class))->getMethods(ReflectionMethod::IS_PUBLIC));
sort($toolApi);
check_broker($toolApi === ['__construct', '__debugInfo', '__serialize', 'run'], 'the Tool-facing API is run() only — no issue, key or credential accessor');

echo "All credential broker checks passed: {$checks} checks.\n";
