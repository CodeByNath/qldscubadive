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
 *      kept, and every planned secret is replaced in one write.
 *   5. Commit check: every stored secret is read again. Any slot not yet on
 *      the new key (a save that raced the rotation) is re-sealed and written
 *      again, up to MAX_PASSES times. Only when every stored secret opens
 *      under the new key are older generations retired — and even then a
 *      generation a secret still names is kept, so no credential can become
 *      unreadable. If the check cannot be satisfied, rotation reports failure
 *      and every key keeps working.
 *
 * The report carries provider:field slot names and counts only — never a key,
 * a wrapped key, a key id, or a plaintext.
 */
final class CredentialRotation
{
    public const MAX_PASSES = 3;

    /** @var \Closure(string): void */
    private \Closure $checkpoint;

    /**
     * @param (\Closure(string): void)|null $checkpoint test seam called at 'planned' (before staging) and 'replaced' (after each write); production passes none
     */
    public function __construct(
        private ConnectionStore $store,
        private CredentialKeyring $keyring,
        ?\Closure $checkpoint = null,
    ) {
        $this->checkpoint = $checkpoint ?? static function (string $phase): void {};
    }

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

        $nextKey = random_bytes(CredentialCipher::KEY_BYTES);
        $next = new CredentialCipher($nextKey);
        $nextKid = (string) $next->keyId();

        [$plan, $expected, $resealed, $unreadable] = $this->plan($this->keyring->cipher(), $next, null);
        if ($unreadable !== []) {
            $report['unreadable'] = $unreadable;
            $report['error'] = 'Some saved API keys cannot be opened. Replace or remove them, then rotate again. Nothing was changed.';
            return $report;
        }
        ($this->checkpoint)('planned');

        $this->keyring->stage($nextKey);
        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            if ($plan !== []) {
                $this->store->replaceSecrets($plan, $expected);
            }
            ($this->checkpoint)('replaced');

            // Commit check against what is stored now, including any save that
            // raced the rotation. The keyring opens old and new generations.
            [$plan, $expected, $stragglers, $unreadable] = $this->plan($this->keyring->cipher(), $next, $nextKid);
            if ($unreadable !== []) {
                $report['unreadable'] = $unreadable;
                $report['error'] = 'Some saved API keys could not be opened during rotation. No older key was retired; replace or remove them, then rotate again.';
                return $report;
            }
            if ($plan === []) {
                $this->keyring->retireUnreferenced($this->referencedKids());
                if ($this->referencedKids() === [] || array_unique($this->referencedKids()) === [$nextKid]) {
                    $report['ok'] = true;
                    $report['resealed'] = array_values(array_unique([...$resealed, ...$stragglers]));
                    return $report;
                }
                // A save landed between the check and retirement: its generation
                // was kept. Check again.
                [$plan, $expected, $stragglers] = $this->plan($this->keyring->cipher(), $next, $nextKid);
            }
            $resealed = array_values(array_unique([...$resealed, ...$stragglers]));
        }

        $report['error'] = 'Saved API keys kept changing while the key was rotating. Every key still works; rotate again.';
        return $report;
    }

    /**
     * Plans re-sealing every stored secret not already under `$skipKid`.
     *
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>, 2: list<string>, 3: list<string>}
     *         plan, expected envelopes, re-sealed slots, unreadable slots
     */
    private function plan(CredentialCipher $opener, CredentialCipher $next, ?string $skipKid): array
    {
        $plan = [];
        $expected = [];
        $resealed = [];
        $unreadable = [];
        foreach ($this->slots() as [$provider, $field, $envelope, $context]) {
            if ($skipKid !== null && is_array($envelope) && ($envelope['kid'] ?? null) === $skipKid && $next->open($envelope, $context) !== null) {
                continue;
            }
            $slot = "{$provider}:{$field}";
            $plaintext = $opener->open($envelope, $context);
            if ($plaintext === null) {
                $unreadable[] = $slot;
                continue;
            }
            $plan[$provider][$field] = $next->seal($plaintext, $context);
            $expected[$provider][$field] = $envelope;
            $resealed[] = $slot;
        }
        return [$plan, $expected, $resealed, $unreadable];
    }

    /** @return list<string> the key id named by every stored secret envelope */
    private function referencedKids(): array
    {
        $kids = [];
        foreach ($this->slots() as [, , $envelope]) {
            if (is_array($envelope) && is_string($envelope['kid'] ?? null)) {
                $kids[] = $envelope['kid'];
            }
        }
        return $kids;
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
