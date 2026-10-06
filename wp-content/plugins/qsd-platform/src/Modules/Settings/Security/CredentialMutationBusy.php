<?php

namespace QSD\Platform\Modules\Settings\Security;

/** Another credential change holds the guard; the caller changed nothing and fails closed. */
final class CredentialMutationBusy extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another change to API keys is in progress. Nothing was changed; try again in a moment.');
    }
}
