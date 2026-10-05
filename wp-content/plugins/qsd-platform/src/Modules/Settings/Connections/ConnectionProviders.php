<?php

namespace QSD\Platform\Modules\Settings\Connections;

use QSD\Platform\Modules\Settings\Connectors\RezdyConnector;

/**
 * The registered connection providers.
 *
 * Only Connectors with a real consumer are listed. Rezdy is the first; Stripe
 * or any later provider is one more ConnectionProviderDefinition here when its
 * Connector exists — the store, projection and routes need no change.
 */
final class ConnectionProviders
{
    /** @return array<string, ConnectionProviderDefinition> */
    public static function all(): array
    {
        $providers = [];
        foreach ([RezdyConnector::definition()] as $definition) {
            $providers[$definition->key] = $definition;
        }
        return $providers;
    }

    public static function find(string $key): ?ConnectionProviderDefinition
    {
        return self::all()[$key] ?? null;
    }
}
