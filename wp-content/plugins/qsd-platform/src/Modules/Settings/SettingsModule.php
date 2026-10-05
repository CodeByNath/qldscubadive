<?php

namespace QSD\Platform\Modules\Settings;

use QSD\Platform\Core\Health;
use QSD\Platform\Modules\Settings\Connections\ConnectionProviders;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Http\ServiceMetaSchemaController;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\CredentialBroker;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialRotationCommand;
use QSD\Platform\Modules\Settings\Security\WpdbRequestKeyStore;

/**
 * SettingsModule — the Settings Station backend: platform/business
 * configuration authority. It wires the Connections/Security Tool (with its
 * at-rest cipher, credential broker and key rotation command) and the
 * Service Meta schema. It owns no domain record, no lifecycle, and no
 * Platform ID family; Service values stay with Service Station.
 */
class SettingsModule
{
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

        // Key rotation is shell-only: registered under WP-CLI, never as a route.
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('qsd credentials', CredentialRotationCommand::class);
        }
    }
}
