<?php

namespace QSD\Platform\Modules\Settings;

use QSD\Platform\Core\Health;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Http\ServiceMetaSchemaController;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;

/**
 * SettingsModule — the Settings Station backend: platform/business
 * configuration authority. It wires the Connections/Security Tool and the
 * Service Meta schema. It owns no domain record, no lifecycle, and no Platform
 * ID family; Service values stay with Service Station.
 */
class SettingsModule
{
    public function register(): void
    {
        (new SettingsConnectionsController(new ConnectionStore()))->register();
        (new ServiceMetaSchemaController(new ServiceMetaSchema()))->register();
        Health::register('settings', static fn() => true);
    }
}
