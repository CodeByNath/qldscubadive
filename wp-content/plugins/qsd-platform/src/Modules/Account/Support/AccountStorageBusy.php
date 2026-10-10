<?php

namespace QSD\Platform\Modules\Account\Support;

/**
 * Thrown when AccountRepository::commit() exhausts its compare-and-swap retry
 * budget. The caller should answer 503 and let the client retry; no partial
 * write ever lands.
 */
final class AccountStorageBusy extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Account Station is busy with another change. Please try again.');
    }
}
