<?php

namespace QSD\Platform\Modules\Settings\Security;

use QSD\Platform\Modules\Settings\Connections\ConnectionStore;

/**
 * `wp qsd credentials reseal` — the shell entry point for key rotation.
 *
 * Registered only under WP-CLI, so it needs server shell access; there is no
 * REST route and no key ever crosses HTTP. Output names provider:field slots
 * and counts only, never a key, key id, or plaintext. See CredentialRotation.
 */
final class CredentialRotationCommand
{
    /**
     * Re-seals every stored provider secret from QSD_CREDENTIAL_KEY_PREVIOUS to QSD_CREDENTIAL_KEY.
     *
     * Writes nothing unless every secret opens under one of the two keys.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function reseal(array $args, array $assoc): void
    {
        $report = (new CredentialRotation(
            new ConnectionStore(),
            CredentialCipher::fromConstant(CredentialRotation::PREVIOUS_CONSTANT),
            CredentialCipher::fromEnvironment(),
        ))->reseal();

        foreach ($report['resealed'] as $slot) {
            \WP_CLI::log("re-sealed {$slot}");
        }
        foreach ($report['already_current'] as $slot) {
            \WP_CLI::log("already under the new key: {$slot}");
        }
        foreach ($report['unreadable'] as $slot) {
            \WP_CLI::warning("opens under neither key: {$slot}");
        }

        if (!$report['ok']) {
            \WP_CLI::error((string) $report['error']);
        }
        \WP_CLI::success(sprintf(
            '%d secret(s) re-sealed, %d already current. Now remove %s from wp-config.php.',
            count($report['resealed']),
            count($report['already_current']),
            CredentialRotation::PREVIOUS_CONSTANT,
        ));
    }
}
