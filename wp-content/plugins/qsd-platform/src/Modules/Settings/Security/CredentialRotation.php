<?php

namespace QSD\Platform\Modules\Settings\Security;

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;

/**
 * CredentialRotation — moves every stored provider secret to a new QSD data
 * key. An administrator runs it from Security → API Keys
 * (`POST admin/settings/security/rotation`); no key material is entered,
 * shown or sent, and no server step is involved.
 *
 *   1. A new data key is generated.
 *   2. Every secret is planned first: opened under the keyring and sealed
 *      again under the new key, bound to the same provider and field, with a
 *      fresh nonce.
 *   3. If any value opens under no generation (or is not an envelope), nothing
 *      is written and the slot is reported unreadable; an unreadable
 *      credential never reads as configured.
 *   4. Otherwise the new generation is stored as active with the old one
 *      kept, every planned secret is replaced in one write, and only then is
 *      the old generation retired. An interruption between steps leaves every
 *      secret openable.
 *
 * The report carries provider:field slot names and counts only — never a key,
 * a wrapped key, a key id, or a plaintext.
 */
final class CredentialRotation
{
    public function __construct(
        private ConnectionStore $store,
        private CredentialKeyring $keyring,
    ) {}

    /**
     * @return array{ok: bool, resealed: list<string>, unreadable: list<string>, error: ?string}
     *         Slot names are "provider:field".
     */
    public function rotate(): array
    {
        $report = ['ok' => false, 'resealed' => [], 'unreadable' => [], 'error' => null];

        if (!$this->keyring->isAvailable()) {
            $report['error'] = 'Secure storage is unavailable on this site, so the encryption key cannot be rotated.';
            return $report;
        }

        $current = $this->keyring->cipher();
        $nextKey = random_bytes(CredentialCipher::KEY_BYTES);
        $next = new CredentialCipher($nextKey);

        $plan = [];
        $expected = [];
        foreach ($this->slots() as [$provider, $field, $envelope, $context]) {
            $slot = "{$provider}:{$field}";
            $plaintext = $current->open($envelope, $context);
            if ($plaintext === null) {
                $report['unreadable'][] = $slot;
                continue;
            }
            $plan[$provider][$field] = $next->seal($plaintext, $context);
            $expected[$provider][$field] = $envelope;
            $report['resealed'][] = $slot;
        }

        if ($report['unreadable'] !== []) {
            $report['resealed'] = [];
            $report['error'] = 'Some saved API keys cannot be opened. Replace or remove them, then rotate again. Nothing was changed.';
            return $report;
        }

        $this->keyring->stage($nextKey);
        if ($plan !== []) {
            $this->store->replaceSecrets($plan, $expected);
        }
        $this->keyring->retireInactive();
        $report['ok'] = true;
        return $report;
    }

    /**
     * Read-only: which generation each stored secret opens under. Writes
     * nothing. Slot names and counts only.
     *
     * @return array{available: bool, generations: int, current: list<string>, previous: list<string>, unreadable: list<string>}
     */
    public function inspect(): array
    {
        $cipher = $this->keyring->cipher();
        $report = [
            'available'   => $this->keyring->isAvailable(),
            'generations' => $this->keyring->status()['generations'],
            'current'     => [],
            'previous'    => [],
            'unreadable'  => [],
        ];
        foreach ($this->slots() as [$provider, $field, $envelope, $context]) {
            $bucket = match (true) {
                $cipher->open($envelope, $context) === null                       => 'unreadable',
                $cipher->isAvailable() && ($envelope['kid'] ?? null) === $cipher->keyId() => 'current',
                default                                                           => 'previous',
            };
            $report[$bucket][] = "{$provider}:{$field}";
        }
        return $report;
    }

    /** @return \Generator<array{string, string, mixed, string}> provider, field, envelope, cipher context */
    private function slots(): \Generator
    {
        foreach ($this->store->providers() as $provider) {
            foreach ($this->store->read($provider)['secrets'] as $field => $envelope) {
                $field = (string) $field;
                yield [$provider, $field, $envelope, CredentialCipher::context($provider, $field)];
            }
        }
    }
}
