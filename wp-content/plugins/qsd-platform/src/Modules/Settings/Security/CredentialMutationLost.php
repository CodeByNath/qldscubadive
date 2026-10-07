<?php

namespace QSD\Platform\Modules\Settings\Security;

/** The holder can no longer prove it owns the guard; it stops before writing anything more. */
final class CredentialMutationLost extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The change to API keys lost its lock before it finished. Nothing more was changed; try again.');
    }
}
