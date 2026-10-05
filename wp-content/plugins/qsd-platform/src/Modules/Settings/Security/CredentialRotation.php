<?php

namespace QSD\Platform\Modules\Settings\Security;

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;

/**
 * CredentialRotation — re-seals every stored provider secret under a new key.
 *
 * Rotation is an explicit, privileged operation, run from the server shell
 * (`wp qsd credentials reseal`), never over REST:
 *
 *   1. Put the old key in `QSD_CREDENTIAL_KEY_PREVIOUS` and the new key in
 *      `QSD_CREDENTIAL_KEY` (wp-config.php, outside the database).
 *   2. Run the re-seal. Every secret is opened with the previous key and sealed
 *      under the new key, bound to the same provider and field.
 *   3. Remove `QSD_CREDENTIAL_KEY_PREVIOUS`.
 *
 * All or nothing: every secret is planned first, and nothing is written unless
 * every one opens under the previous key or is already sealed under the new
 * key. A value that opens under neither is reported unreadable and the store
 * is left untouched, so an unreadable credential never reads as configured.
 * The report carries provider and field names and counts only — never a key,
 * a key id, or a plaintext.
 */
final class CredentialRotation
{
    public const PREVIOUS_CONSTANT = 'QSD_CREDENTIAL_KEY_PREVIOUS';

    public function __construct(
        private ConnectionStore $store,
        private CredentialCipher $previous,
        private CredentialCipher $current,
    ) {}

    /**
     * @return array{ok: bool, resealed: list<string>, already_current: list<string>, unreadable: list<string>, error: ?string}
     *         Slot names are "provider:field".
     */
    public function reseal(): array
    {
        $report = ['ok' => false, 'resealed' => [], 'already_current' => [], 'unreadable' => [], 'error' => null];

        if (!$this->current->isAvailable()) {
            $report['error'] = 'The new key (QSD_CREDENTIAL_KEY) is missing or invalid.';
            return $report;
        }
        if (!$this->previous->isAvailable()) {
            $report['error'] = 'The previous key (QSD_CREDENTIAL_KEY_PREVIOUS) is missing or invalid.';
            return $report;
        }
        if ($this->previous->keyId() === $this->current->keyId()) {
            $report['error'] = 'The previous and new keys are the same key.';
            return $report;
        }

        $plan = [];
        foreach ($this->store->providers() as $provider) {
            $secrets = $this->store->read($provider)['secrets'];
            foreach ($secrets as $field => $envelope) {
                $slot = "{$provider}:{$field}";
                $context = CredentialCipher::context($provider, (string) $field);

                if ($this->current->open($envelope, $context) !== null) {
                    $report['already_current'][] = $slot;
                    continue;
                }
                $plaintext = $this->previous->open($envelope, $context);
                if ($plaintext === null) {
                    $report['unreadable'][] = $slot;
                    continue;
                }
                $plan[$provider][$field] = $this->current->seal($plaintext, $context);
                $report['resealed'][] = $slot;
            }
        }

        if ($report['unreadable'] !== []) {
            $report['resealed'] = [];
            $report['error'] = 'Some secrets open under neither key; nothing was re-sealed.';
            return $report;
        }

        if ($plan !== []) {
            $this->store->replaceSecrets($plan);
        }
        $report['ok'] = true;
        return $report;
    }
}
