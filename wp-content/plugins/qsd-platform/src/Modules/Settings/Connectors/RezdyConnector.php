<?php

namespace QSD\Platform\Modules\Settings\Connectors;

use QSD\Platform\Modules\Settings\Connections\ConnectionProviderDefinition;
use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;

/**
 * RezdyConnector — the first Connector implementation, not platform architecture.
 *
 * Scope stops at the connection seam: what an administrator configures
 * (environment + API key) and whether that configuration is complete. It makes
 * no HTTP call, maps no product, and defines no import flow.
 *
 * OWNER CHECKPOINT: Rezdy product/service mapping, importer transformation and
 * canonical Service import wait for the Owner's pre-built Rezdy importer. Do
 * not extend this class into an importer before that system is reviewed.
 */
final class RezdyConnector
{
    public const PROVIDER = 'rezdy';

    private const BASE_URLS = [
        'production' => 'https://api.rezdy.com/v1/',
        'staging'    => 'https://api.rezdy-staging.com/v1/',
    ];

    public static function definition(): ConnectionProviderDefinition
    {
        return new ConnectionProviderDefinition(
            self::PROVIDER,
            'Rezdy',
            'Booking and product supplier connection. Product import and mapping are not available yet.',
            [
                [
                    'key'      => 'environment',
                    'label'    => 'Environment',
                    'type'     => ConnectionProviderDefinition::FIELD_SELECT,
                    'required' => true,
                    'options'  => ['production' => 'Production', 'staging' => 'Staging (sandbox)'],
                ],
                [
                    'key'      => 'api_key',
                    'label'    => 'API key',
                    'type'     => ConnectionProviderDefinition::FIELD_SECRET,
                    'required' => true,
                ],
            ],
        );
    }

    public function __construct(private ConnectorCredentials $credentials) {}

    /**
     * Server-side connection state for a future Rezdy consumer. Never carries
     * the API key.
     *
     * @return array{configured: bool, environment: ?string, base_url: ?string}
     */
    public function connectionState(): array
    {
        $environment = $this->credentials->config(self::PROVIDER, 'environment');
        return [
            'configured'  => $this->credentials->isConfigured(self::definition()),
            'environment' => $environment,
            'base_url'    => $environment !== null ? (self::BASE_URLS[$environment] ?? null) : null,
        ];
    }
}
