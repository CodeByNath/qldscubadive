<?php

namespace QSD\Platform\Modules\Settings;

use QSD\Platform\Core\Health;
use QSD\Platform\Modules\Settings\Connections\ConnectionProviders;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Http\ServiceMetaSchemaController;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceElementDefinitions;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\CredentialBroker;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\WpdbRequestKeyStore;

/**
 * SettingsModule — the Settings Station backend: platform/business
 * configuration authority. It wires the Connections/Security Tool (with its
 * at-rest cipher and credential broker) and the Service Element definition
 * schema. It owns no domain record, no lifecycle, and no Platform ID family;
 * Service values stay with Service Station.
 */
class SettingsModule
{
    /** The read-only Element definition capability Service Station consumes. */
    public function serviceElementDefinitions(): ServiceElementDefinitions
    {
        return new ServiceElementDefinitions(new ServiceMetaSchema());
    }

    /**
     * The credential broker — the only way a consumer obtains provider
     * authority. No provider declares a brokered scope or operation yet, so
     * every issuance is refused until a reviewed Connector adds one.
     */
    public function credentialBroker(): CredentialBroker
    {
        return new CredentialBroker(
            ConnectionProviders::all(),
            new ConnectorCredentials(new ConnectionStore(), CredentialCipher::fromEnvironment()),
            new WpdbRequestKeyStore(),
            new BrokerAuditLog(),
        );
    }

    public function register(): void
    {
        (new SettingsConnectionsController(new ConnectionStore(), CredentialCipher::fromEnvironment()))->register();
        (new ServiceMetaSchemaController(new ServiceMetaSchema()))->register();
        Health::register('settings', static fn() => true);
    }
}
