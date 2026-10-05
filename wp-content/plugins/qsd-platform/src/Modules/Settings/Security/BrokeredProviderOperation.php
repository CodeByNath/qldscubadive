<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * A provider operation the broker performs on a consumer's behalf after a
 * request key is consumed. Implementations are Settings-owned Connectors: they
 * are the only code that ever sees `ProviderSecrets`. The returned array goes
 * back to the consumer and must never contain a secret — the broker refuses a
 * result that does.
 */
interface BrokeredProviderOperation
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function perform(string $scope, ProviderSecrets $secrets, array $params): array;
}
