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
 *      kept, every planned secret is replaced in one write, every stored
 *      secret is confirmed on the new key, and only then are older
 *      generations retired (never one a stored secret still names).
 *
 * The whole operation runs inside the CredentialMutationGuard that every
 * credential write also holds, so no save can seal under a generation while
 * it is being retired, and rotation never commits while a save is in flight.
 * If the guard is busy, rotation changes nothing and reports failure.
 *
 * The report carries provider:field slot names and counts only — never a key,
 * a wrapped key, a key id, or a plaintext.
 */
final class CredentialRotation
{
    /** @var \Closure(string): void */
    private \Closure $checkpoint;

    /**
     * @param (\Closure(string): void)|null $checkpoint test seam called at 'planned' and 'replaced' while the guard is held; production passes none
     */
    public function __construct(
        private ConnectionStore $store,
        private CredentialKeyring $keyring,
        private CredentialMutationGuard $guard,
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
        if (!$this->keyring->isAvailable()) {
            return $this->failed('Secure storage is unavailable on this site, so the encryption key cannot be rotated.');
        }
        try {
            return $this->guard->hold(fn(): array => $this->rotateHeld());
        } catch (CredentialMutationBusy $e) {
            return $this->failed($e->getMessage());
        }
    }

    /** @return array{ok: bool, resealed: list<string>, unreadable: list<string>, error: ?string} */
    private function rotateHeld(): array
    {
        $nextKey = random_bytes(CredentialCipher::KEY_BYTES);
        $next = new CredentialCipher($nextKey);
        $nextKid = (string) $next->keyId();

        [$plan, $expected, $resealed, $unreadable] = $this->plan($this->keyring->cipher(), $next, null);
        if ($unreadable !== []) {
            return $this->failed('Some saved API keys cannot be opened. Replace or remove them, then rotate again. Nothing was changed.', $unreadable);
        }
        ($this->checkpoint)('planned');

        $this->keyring->stage($nextKey);
        if ($plan !== []) {
            $this->store->replaceSecrets($plan, $expected);
        }
        ($this->checkpoint)('replaced');

        // Commit check: every stored secret must now open under the new key.
        // Under the guard nothing else writes, so anything left means retiring
        // would be unsafe: keep every generation and report failure.
        [$left, , , $lost] = $this->plan($this->keyring->cipher(), $next, $nextKid);
        if ($left !== [] || $lost !== []) {
            return $this->failed('The rotation could not confirm every saved API key on the new key. No older key was retired, and every key still works.', $lost);
        }
        $this->keyring->retireUnreferenced($this->referencedKids());

        return ['ok' => true, 'resealed' => $resealed, 'unreadable' => [], 'error' => null];
    }

    /**
     * @param list<string> $unreadable
     * @return array{ok: false, resealed: list<string>, unreadable: list<string>, error: string}
     */
    private function failed(string $error, array $unreadable = []): array
    {
        return ['ok' => false, 'resealed' => [], 'unreadable' => $unreadable, 'error' => $error];
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
