<?php

/*
 * FILE INDEX
 *
 * VALIDATION_RUN       The ordered run and its report
 * VALIDATION_STORAGE   Encrypted-at-rest evidence from real option storage
 * VALIDATION_KEYS      Request-key lifecycle against the real store
 * VALIDATION_AUDIT     Audit evidence and the final leak guard
 *
 * Search: SECTION: VALIDATION_RUN ... SECTION: VALIDATION_AUDIT
 */

namespace QSD\Platform\Modules\Settings\Security;

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;

/**
 * BrokerValidation — the Security Phase 2 runtime check, run on a real
 * WordPress install against its real database.
 *
 * One run, for the server-derived WordPress user and the allow-listed
 * `settings.security-validation` caller, proves on the live storage:
 *
 *   - stored provider secrets are encryption envelopes that open under the
 *     current key, with no plaintext in the option (plus rotation readiness);
 *   - a request key is stored only as its hash, bound to provider, scope,
 *     caller, user and a per-run subject;
 *   - the key is consumed once and the provider operation runs server-side —
 *     the run's single provider call;
 *   - replay, expiry, a binding mismatch (which burns the key) and the sweep of
 *     a never-presented key are refused or removed by the real store;
 *   - every key the run issued is gone afterwards;
 *   - the audit trail records each outcome with no key, hash or secret.
 *
 * The report is safe metadata only and is checked for leaks before it leaves.
 * It never contains a request key, a key hash, a secret, or a key id.
 */
final class BrokerValidation
{
    public const CALLER = 'settings.security-validation';

    private const ENVELOPE_FIELDS = ['alg', 'ct', 'kid', 'nonce', 'v'];

    /** @var \Closure(int): void */
    private \Closure $sleep;

    /** @var list<array{check: string, ok: bool, detail?: mixed}> */
    private array $checks = [];

    /** @var list<string> values the report must never contain */
    private array $forbidden = [];

    /** @param (\Closure(int): void)|null $sleep */
    public function __construct(
        private CredentialBroker $broker,
        private RequestKeyStore $keys,
        private BrokerAuditLog $audit,
        private ConnectionStore $store,
        private CredentialCipher $cipher,
        private CredentialRotation $rotation,
        private string $provider,
        private string $scope,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $seconds): void { sleep($seconds); };
    }

    // =======================================================================
    // SECTION: VALIDATION_RUN
    // =======================================================================

    /**
     * @param int $userId the authenticated WordPress user, derived by the caller from the session
     * @return array{passed: bool, identity: array<string, mixed>, provider_check: ?array<string, mixed>, rotation: array<string, mixed>, checks: list<array{check: string, ok: bool, detail?: mixed}>}
     */
    public function run(int $userId): array
    {
        $this->checks = [];
        $this->forbidden = [];

        $identity = ['user_id' => $userId, 'caller' => self::CALLER, 'source' => 'server'];
        $this->check('user identity is the authenticated WordPress session user', $userId > 0, ['user_id' => $userId]);

        $this->checkStorage();
        $rotation = $this->rotation->inspect();
        $this->check('every stored secret opens under the current key (rotation state)', $rotation['previous'] === [] && $rotation['unreadable'] === [], [
            'previous_key_defined' => $rotation['previous_key'],
            'current'              => $rotation['current'],
            'previous'             => $rotation['previous'],
            'unreadable'           => $rotation['unreadable'],
        ]);

        $subject = 'val_' . bin2hex(random_bytes(6));
        $providerCheck = $userId > 0 ? $this->checkRequestKeys(new BrokerGrant($this->provider, $this->scope, self::CALLER, $userId, $subject)) : null;
        $this->checkAudit($subject);

        $report = [
            'passed'         => !in_array(false, array_column($this->checks, 'ok'), true),
            'identity'       => $identity,
            'provider_check' => $providerCheck,
            'rotation'       => ['previous_key_defined' => $rotation['previous_key']] + array_intersect_key($rotation, array_flip(['current', 'previous', 'unreadable'])),
            'checks'         => $this->checks,
        ];
        return $this->guard($report);
    }

    private function check(string $name, bool $ok, mixed $detail = null): bool
    {
        $entry = ['check' => $name, 'ok' => $ok];
        if ($detail !== null) {
            $entry['detail'] = $detail;
        }
        $this->checks[] = $entry;
        return $ok;
    }

    // =======================================================================
    // SECTION: VALIDATION_STORAGE
    // =======================================================================

    private function checkStorage(): void
    {
        $this->check('credential encryption key is configured on this server', $this->cipher->isAvailable());

        $record = $this->store->read($this->provider);
        $stored = serialize($record);
        $fields = [];
        $allSealed = $record['secrets'] !== [];
        foreach ($record['secrets'] as $field => $envelope) {
            $field = (string) $field;
            $plaintext = $this->cipher->open($envelope, CredentialCipher::context($this->provider, $field));
            if ($plaintext !== null) {
                $this->forbidden[] = $plaintext;
            }
            $shape = is_array($envelope) ? array_keys($envelope) : [];
            sort($shape);
            $entry = [
                'field'           => $field,
                'envelope'        => $shape === self::ENVELOPE_FIELDS,
                'opens'           => $plaintext !== null,
                'plaintext_absent' => $plaintext === null || !str_contains($stored, $plaintext),
            ];
            $allSealed = $allSealed && $entry['envelope'] && $entry['opens'] && $entry['plaintext_absent'];
            $fields[] = $entry;
        }
        $this->check("stored {$this->provider} secrets are encryption envelopes with no plaintext in the option", $allSealed, ['option' => ConnectionStore::OPTION, 'fields' => $fields]);
    }

    // =======================================================================
    // SECTION: VALIDATION_KEYS
    // =======================================================================

    /** @return array<string, mixed>|null the provider operation's safe result */
    private function checkRequestKeys(BrokerGrant $grant): ?array
    {
        $issued = [];

        // 1. Issue: hash-only, bound record in the real store.
        try {
            $first = $this->issue($grant, CredentialBroker::DEFAULT_TTL, $issued);
        } catch (BrokerRejected $e) {
            $this->check('a request key is issued for the allow-listed caller', false, ['reason' => $e->reason]);
            return null;
        }
        $row = $this->keys->find(hash('sha256', $first->key));
        $this->check('the issued key is stored only as its hash, bound to provider, scope, caller, user and subject', $row !== null
            && !str_contains((string) json_encode($row), $first->key)
            && $grant->matches($row)
            && (int) $row['expires_at'] - (int) $row['issued_at'] === CredentialBroker::DEFAULT_TTL, ['request_id' => $first->requestId, 'ttl_seconds' => CredentialBroker::DEFAULT_TTL]);

        // 2. Consume once and perform — the run's only provider call.
        $result = null;
        try {
            $result = $this->broker->perform($first->key, $grant);
            $this->check('the key is consumed once and the provider operation runs server-side', $this->keys->find(hash('sha256', $first->key)) === null, ['outcome' => $result['outcome'] ?? null]);
        } catch (BrokerRejected $e) {
            $this->check('the key is consumed once and the provider operation runs server-side', false, ['reason' => $e->reason]);
        }

        // 3. Replay of the consumed key.
        $this->expectRejected('a replayed key is refused', fn() => $this->broker->perform($first->key, $grant), BrokerRejected::UNKNOWN_KEY);

        // 4. Expiry, and the sweep of a key that is never presented.
        try {
            $short = $this->issue($grant, 1, $issued);
            $abandoned = $this->issue($grant, 1, $issued);
            ($this->sleep)(2);
            $this->expectRejected('an expired key is refused', fn() => $this->broker->perform($short->key, $grant), BrokerRejected::EXPIRED);

            // 5. Binding mismatch burns the key; this issuance also sweeps the abandoned one.
            $bound = $this->issue($grant, CredentialBroker::DEFAULT_TTL, $issued);
            $this->check('issuing sweeps an expired, never-presented key from the store', $this->keys->find(hash('sha256', $abandoned->key)) === null);
            $wrong = new BrokerGrant($grant->provider, $grant->scope, $grant->caller, $grant->userId, $grant->subject . 'x');
            $this->expectRejected('a key presented with the wrong binding is refused', fn() => $this->broker->perform($bound->key, $wrong), BrokerRejected::BINDING_MISMATCH);
            $this->expectRejected('a binding mismatch burns the key', fn() => $this->broker->perform($bound->key, $grant), BrokerRejected::UNKNOWN_KEY);
        } catch (BrokerRejected $e) {
            $this->check('request keys can be issued for the lifecycle checks', false, ['reason' => $e->reason]);
        }

        // 6. Nothing the run issued is left behind.
        $left = count(array_filter($issued, fn(string $key) => $this->keys->find(hash('sha256', $key)) !== null));
        $this->check('no request key issued by this run is left in the store', $left === 0, ['issued' => count($issued), 'remaining' => $left]);

        return $result;
    }

    /** @param list<string> $issued */
    private function issue(BrokerGrant $grant, int $ttl, array &$issued): IssuedRequestKey
    {
        $key = $this->broker->issue($grant, $ttl);
        $issued[] = $key->key;
        $this->forbidden[] = $key->key;
        $this->forbidden[] = hash('sha256', $key->key);
        return $key;
    }

    private function expectRejected(string $name, callable $attempt, string $reason): void
    {
        try {
            $attempt();
            $this->check($name, false, ['reason' => null]);
        } catch (BrokerRejected $e) {
            $this->check($name, $e->reason === $reason, ['reason' => $e->reason]);
        }
    }

    // =======================================================================
    // SECTION: VALIDATION_AUDIT
    // =======================================================================

    private function checkAudit(string $subject): void
    {
        $entries = array_values(array_filter($this->audit->entries(), static fn(array $e) => ($e['subject'] ?? null) === $subject));
        $events = array_count_values(array_map(static fn(array $e) => (string) $e['event'], $entries));
        ksort($events);
        $this->check('the audit records this run\'s outcomes', $entries !== [], ['events' => $events]);
        $this->check('the audit holds no request key, key hash or secret', !$this->leaks(serialize($this->audit->entries())));
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function guard(array $report): array
    {
        if (!$this->leaks(serialize($report)) && !$this->leaks((string) json_encode($report))) {
            return $report;
        }
        return [
            'passed'         => false,
            'identity'       => $report['identity'],
            'provider_check' => null,
            'rotation'       => [],
            'checks'         => [['check' => 'the report carries no request key, key hash or secret', 'ok' => false]],
        ];
    }

    private function leaks(string $text): bool
    {
        $keyId = $this->cipher->keyId();
        foreach ([...$this->forbidden, ...($keyId !== null ? [$keyId] : [])] as $value) {
            if ($value !== '' && str_contains($text, $value)) {
                return true;
            }
        }
        return false;
    }
}
