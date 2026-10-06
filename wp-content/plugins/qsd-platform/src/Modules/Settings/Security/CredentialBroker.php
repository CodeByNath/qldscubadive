<?php

/*
 * FILE INDEX
 *
 * BROKER_ISSUE     Issue a short-lived, single-use, bound request key
 * BROKER_PERFORM   Consume the key once, then perform the provider operation
 * BROKER_HELPERS   Hashing, key generation, secret-leak guard
 *
 * Search: SECTION: BROKER_ISSUE ... SECTION: BROKER_HELPERS
 */

namespace QSD\Platform\Modules\Settings\Security;

use QSD\Platform\Modules\Settings\Connections\ConnectionProviderDefinition;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;

/**
 * CredentialBroker — Connections/Security as a credential broker.
 *
 * A consumer (a future importer, a booking sync) never receives a stored
 * long-lived provider credential. Instead:
 *
 *   1. it asks for narrowly scoped authority — `issue(BrokerGrant)` — and gets
 *      a cryptographically random request key bound to provider + scope +
 *      caller component + WordPress user (+ optional subject), valid for a
 *      short TTL. The caller must be on the server-side allow-list for that
 *      provider scope;
 *   2. only the SHA-256 hash of the key is persisted, with safe metadata;
 *   3. it presents the key with the same binding — `perform()` — and the
 *      broker consumes it atomically, exactly once;
 *   4. only then does the broker decrypt the provider credential, server-side,
 *      and hand it to the Settings-owned provider operation; the consumer gets
 *      that operation's result, which is checked to carry no secret.
 *
 * Replay, expiry, and any binding mismatch are refused; a key presented with
 * the wrong binding is burned, not left usable. Every outcome is audited
 * without keys or secrets. A request key is a security artifact, never a
 * Platform ID.
 */
final class CredentialBroker
{
    public const DEFAULT_TTL = 60;
    public const MAX_TTL     = 300;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param array<string, ConnectionProviderDefinition> $providers  provider key => definition
     * @param array<string, BrokeredProviderOperation>    $operations provider key => Settings-owned operation
     * @param (\Closure(): int)|null                      $clock
     * @param array<string, list<string>>                 $callers    caller component => allowed "provider:scope" pairs
     */
    public function __construct(
        private array $providers,
        private ConnectorCredentials $credentials,
        private RequestKeyStore $store,
        private BrokerAuditLog $audit,
        private array $operations = [],
        ?\Closure $clock = null,
        private array $callers = [],
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    // =======================================================================
    // SECTION: BROKER_ISSUE
    // =======================================================================

    public function issue(BrokerGrant $grant, int $ttl = self::DEFAULT_TTL): IssuedRequestKey
    {
        $now = ($this->clock)();
        foreach ($this->store->sweepExpired($now) as $stale) {
            $this->audit->record(BrokerAuditLog::EXPIRED, $stale, $now);
        }

        $definition = $this->providers[$grant->provider] ?? null;
        if ($definition === null) {
            throw new BrokerRejected(BrokerRejected::UNKNOWN_PROVIDER, 'Unknown connection provider.');
        }
        if (!in_array($grant->scope, $definition->scopes, true)) {
            throw new BrokerRejected(BrokerRejected::UNDECLARED_SCOPE, "{$definition->label} does not offer the requested scope.");
        }
        // Caller identity is a server-side allow-list, never a client claim:
        // a component not listed for this provider scope gets no key.
        if (!in_array("{$grant->provider}:{$grant->scope}", $this->callers[$grant->caller] ?? [], true)) {
            throw new BrokerRejected(BrokerRejected::UNKNOWN_CALLER, 'The calling component is not allowed this provider scope.');
        }
        if (!$this->credentials->isConfigured($definition)) {
            throw new BrokerRejected(BrokerRejected::NOT_CONFIGURED, "{$definition->label} is not configured.");
        }

        $ttl = max(1, min(self::MAX_TTL, $ttl));
        $key = $this->generateKey();
        $record = ['request_id' => 'req_' . bin2hex(random_bytes(8))] + $grant->toArray() + [
            'issued_at'  => $now,
            'expires_at' => $now + $ttl,
        ];
        $this->store->put($this->hash($key), $record);
        $this->audit->record(BrokerAuditLog::ISSUED, $record, $now);

        return new IssuedRequestKey($record['request_id'], $key, $record['expires_at']);
    }

    // =======================================================================
    // SECTION: BROKER_PERFORM
    // =======================================================================

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed> the provider operation's result
     */
    public function perform(#[\SensitiveParameter] string $key, BrokerGrant $grant, array $params = []): array
    {
        $now = ($this->clock)();
        $hash = $this->hash($key);
        $record = $this->store->find($hash);

        if ($record === null) {
            $this->audit->record(BrokerAuditLog::REJECTED_UNKNOWN, $grant->toArray(), $now);
            throw new BrokerRejected(BrokerRejected::UNKNOWN_KEY, 'The request key is unknown or has already been used.');
        }
        // One-time consumption: whoever claims the row owns this key; a
        // concurrent or repeated presentation loses here.
        if (!$this->store->claim($hash)) {
            $this->audit->record(BrokerAuditLog::REJECTED_REPLAY, $record, $now);
            throw new BrokerRejected(BrokerRejected::REPLAYED, 'The request key has already been used.');
        }
        if ($now > (int) $record['expires_at']) {
            $this->audit->record(BrokerAuditLog::REJECTED_EXPIRED, $record, $now);
            throw new BrokerRejected(BrokerRejected::EXPIRED, 'The request key has expired.');
        }
        if (!$grant->matches($record)) {
            $this->audit->record(BrokerAuditLog::REJECTED_BINDING, $record, $now);
            throw new BrokerRejected(BrokerRejected::BINDING_MISMATCH, 'The request key was not issued for this provider, scope and caller.');
        }

        $operation = $this->operations[$grant->provider] ?? null;
        $definition = $this->providers[$grant->provider] ?? null;
        if ($operation === null || $definition === null) {
            $this->audit->record(BrokerAuditLog::FAILED, $record, $now);
            throw new BrokerRejected(BrokerRejected::NO_OPERATION, 'No brokered operation is available for this provider.');
        }

        $credentials = $this->credentials;
        $secretValues = [];
        $secrets = new ProviderSecrets($grant->provider, static function (string $field) use ($credentials, $definition, &$secretValues): ?string {
            if (!$definition->isSecret($field)) {
                return null;
            }
            $value = $credentials->secret($definition->key, $field);
            if ($value !== null) {
                $secretValues[] = $value;
            }
            return $value;
        });

        try {
            $result = $operation->perform($grant->scope, $secrets, $params);
        } catch (\Throwable) {
            $this->audit->record(BrokerAuditLog::FAILED, $record, $now);
            throw new BrokerRejected(BrokerRejected::OPERATION_FAILED, 'The provider operation failed.');
        }

        if ($this->containsSecret($result, $secretValues)) {
            $this->audit->record(BrokerAuditLog::FAILED, $record, $now);
            throw new BrokerRejected(BrokerRejected::SECRET_IN_RESULT, 'The provider operation tried to return a credential; the result was withheld.');
        }

        $this->audit->record(BrokerAuditLog::USED, $record, $now);
        return $result;
    }

    // =======================================================================
    // SECTION: BROKER_HELPERS
    // =======================================================================

    /** 32 random bytes, base64url — the key itself is never stored. */
    private function generateKey(): string
    {
        return 'qrk_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hash(#[\SensitiveParameter] string $key): string
    {
        return hash('sha256', $key);
    }

    /** @param list<string> $secrets */
    private function containsSecret(mixed $value, array $secrets): bool
    {
        if ($secrets === []) {
            return false;
        }
        if (is_array($value)) {
            foreach ($value as $k => $item) {
                if ($this->containsSecret((string) $k, $secrets) || $this->containsSecret($item, $secrets)) {
                    return true;
                }
            }
            return false;
        }
        if (is_object($value)) {
            return true; // results are plain data; an object could carry anything
        }
        if (is_scalar($value)) {
            foreach ($secrets as $secret) {
                if ($secret !== '' && str_contains((string) $value, $secret)) {
                    return true;
                }
            }
        }
        return false;
    }
}
