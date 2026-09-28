<?php

namespace QSD\Platform\Modules\Service;

use QSD\Platform\Modules\Service\Http\ServiceController;
use QSD\Platform\PlatformIdentifier\PlatformIdentifierStation;

/**
 * Service module — backend only.
 *
 * The single backend owner of the qsd_service entity. Holds the catalogue,
 * detail, draft, settle/revert, lifecycle, and pool-creation handlers that
 * previously lived in Admin\Http\AdminServicesController, with route paths,
 * payloads, validation, permissions, and persistence unchanged.
 *
 * This module is the boundary: other modules wire through here and may import
 * Support\ServicePools (the Service-owned pool write path). Nothing outside may import ServiceController, its
 * private helpers, or its route registration.
 *
 * Deliberately narrow. qsd_service persistence is WordPress post/meta, so there
 * is no repository; the entity's storage keys and REST argument definitions
 * live in Support\ServiceSchema. Post type and taxonomy registration stay
 * with the shared Core registrars, which register every platform entity.
 *
 * The Service admin frontend peer lives in resources/ts/service-station/.
 */
class ServiceModule
{
    public function __construct(private PlatformIdentifierStation $platformIdentifiers) {}

    public function register(): void
    {
        (new ServiceController($this->platformIdentifiers))->register();
    }
}
