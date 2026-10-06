<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * BrokeredAccess — the only provider authority a Tool (a future importer, a
 * booking sync) receives.
 *
 * It is bound, server-side, to one allow-listed caller component
 * (`SettingsModule::brokeredAccess()`), and derives the WordPress user from the
 * session on every call. `run()` issues a short-lived request key for that
 * caller, user and optional subject, consumes it at once, and returns only the
 * Settings-owned provider operation's result — which the broker has already
 * checked for secrets. The Tool never holds the broker, a request key,
 * ConnectorCredentials or a credential, and cannot choose its caller or user.
 */
final class BrokeredAccess
{
    /** @param \Closure(): int $currentUser the authenticated session user's id */
    public function __construct(
        private CredentialBroker $broker,
        public readonly string $caller,
        private \Closure $currentUser,
    ) {}

    /**
     * Runs one declared provider scope for the session user.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> the provider operation's safe result
     * @throws BrokerRejected on any refusal (no user, caller not allowed the scope, not configured, …)
     */
    public function run(string $provider, string $scope, array $params = [], ?string $subject = null): array
    {
        $userId = ($this->currentUser)();
        if ($userId <= 0) {
            throw new BrokerRejected(BrokerRejected::UNAUTHENTICATED, 'Provider access needs a signed-in user.');
        }
        $grant = new BrokerGrant($provider, $scope, $this->caller, $userId, $subject);
        $issued = $this->broker->issue($grant);
        return $this->broker->perform($issued->key, $grant, $params);
    }

    public function __debugInfo(): array
    {
        return ['caller' => $this->caller];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Brokered access cannot be serialised.');
    }
}
