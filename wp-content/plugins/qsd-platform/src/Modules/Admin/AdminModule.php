<?php

namespace QSD\Platform\Modules\Admin;

use QSD\Platform\Core\Health;
use QSD\Platform\Modules\Admin\Http\AdminCategoriesController;
use QSD\Platform\Modules\Admin\Http\AdminController;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

/**
 * AdminModule wires the authenticated admin REST controllers. It owns backend
 * validation and orchestration for Categories and the overview route.
 * It hosts no frontend surface; the admin frontend is the Admin Station, and
 * access is owned by Core\PlatformAccess.
 */
class AdminModule
{
    public function __construct(private PlatformIdentifierStation $platformIdentifiers) {}

    public function register(): void
    {
        (new AdminController())->register();
        (new AdminCategoriesController($this->platformIdentifiers))->register();
        Health::register('admin', static fn() => true);
    }
}
