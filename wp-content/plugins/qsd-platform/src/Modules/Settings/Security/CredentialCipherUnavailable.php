<?php

namespace QSD\Platform\Modules\Settings\Security;

/** No valid QSD_CREDENTIAL_KEY is configured, so a secret cannot be sealed. */
final class CredentialCipherUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Credential encryption is not configured on this server, so secrets cannot be saved.');
    }
}
