<?php

namespace QSD\Platform\Modules\Settings;

use QSD\Platform\Core\Health;
use QSD\Platform\Modules\Settings\Connections\ConnectionProviders;
use QSD\Platform\Modules\Settings\Connections\ConnectionStore;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Http\ServiceMetaSchemaController;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnectionCheck;
use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;
use QSD\Platform\Modules\Settings\Http\SettingsConnectionsController;
use QSD\Platform\Modules\Settings\Http\SettingsSecurityController;
use QSD\Platform\Modules\Settings\ServiceMeta\ServiceMetaSchema;
use QSD\Platform\Modules\Settings\Security\BrokerAuditLog;
use QSD\Platform\Modules\Settings\Security\CredentialBroker;
use QSD\Platform\Modules\Settings\Security\BrokerValidation;
use QSD\Platform\Modules\Settings\Security\CredentialCipher;
use QSD\Platform\Modules\Settings\Security\CredentialRotation;
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
     * Caller components allowed to request each provider scope. Caller
     * identity is fixed server code, never a client value. The only entry is
     * the Phase 2 runtime validation run and Rezdy's read-only check.
     *
     * @return array<string, list<string>> caller => "provider:scope" pairs
     */
    public static function brokerCallers(): array
    {
        return [
            BrokerValidation::CALLER => [RezdyConnector::PROVIDER . ':' . RezdyConnector::SCOPE_VERIFY],
        ];
    }

    /** The credential broker — the only way a consumer obtains provider authority. */
    public function credentialBroker(): CredentialBroker
    {
        $credentials = new ConnectorCredentials(new ConnectionStore(), CredentialCipher::fromEnvironment());
        return new CredentialBroker(
            ConnectionProviders::all(),
            $credentials,
            new WpdbRequestKeyStore(),
            new BrokerAuditLog(),
            [RezdyConnector::PROVIDER => new RezdyConnectionCheck($credentials)],
            null,
            self::brokerCallers(),
        );
    }

    /** The Phase 2 runtime validation run against the real install. */
    public function brokerValidation(): BrokerValidation
    {
        $store = new ConnectionStore();
        $cipher = CredentialCipher::fromEnvironment();
        return new BrokerValidation(
            $this->credentialBroker(),
            new WpdbRequestKeyStore(),
            new BrokerAuditLog(),
            $store,
            $cipher,
            new CredentialRotation($store, CredentialCipher::fromConstant(CredentialRotation::PREVIOUS_CONSTANT), $cipher),
            RezdyConnector::PROVIDER,
            RezdyConnector::SCOPE_VERIFY,
        );
    }

    public function register(): void
    {
        (new SettingsConnectionsController(new ConnectionStore(), CredentialCipher::fromEnvironment()))->register();
        (new SettingsSecurityController(fn(): BrokerValidation => $this->brokerValidation()))->register();
        (new ServiceMetaSchemaController(new ServiceMetaSchema()))->register();
        Health::register('settings', static fn() => true);

        // Key rotation is shell-only: registered under WP-CLI, never as a route.
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('qsd credentials', CredentialRotationCommand::class);
        }
    }
}
