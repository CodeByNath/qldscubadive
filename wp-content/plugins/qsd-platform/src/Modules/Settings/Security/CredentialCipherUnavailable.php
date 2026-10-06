<?php

namespace QSD\Platform\Modules\Settings\Security;

/** Secure storage is unavailable on this install (no wrapping key or no libsodium), so a secret cannot be sealed. */
final class CredentialCipherUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Secure storage is unavailable on this site, so API keys cannot be saved.');
    }
}
