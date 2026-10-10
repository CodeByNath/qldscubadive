<?php

namespace QSD\Platform\Modules\Account;

use QSD\Platform\Modules\Account\Http\AccountController;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

/**
 * Account module — backend only.
 *
 * Phase A: the single backend owner of the Account singleton hierarchy's
 * identity bootstrap (Account → Settings → Tools → Profile). Brand content,
 * draft/settle, and Publish/Disable/Enable lifecycle are not implemented yet
 * (Phase B). Account is a singleton aggregate (one WordPress options row via
 * Support\AccountRepository), not a post/meta collection, so there is no
 * catalogue and no numeric native reference.
 */
class AccountModule
{
    public function __construct(private PlatformIdentifierStation $platformIdentifiers) {}

    public function register(): void
    {
        (new AccountController($this->platformIdentifiers))->register();
    }
}
