<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * The narrow authority a request key carries, and the binding a consumer must
 * present again to use it: provider + scope + caller (server component and
 * WordPress user), optionally narrowed to one subject such as a Service
 * `QSDS` or an import operation id.
 */
final class BrokerGrant
{
    private const CALLER_PATTERN  = '/^[a-z][a-z0-9_.-]{1,63}$/';
    private const SCOPE_PATTERN   = '/^[a-z][a-z0-9_.:-]{1,63}$/';
    private const SUBJECT_PATTERN = '/^[A-Za-z0-9_.:-]{1,64}$/';

    public function __construct(
        public readonly string $provider,
        public readonly string $scope,
        public readonly string $caller,
        public readonly int $userId,
        public readonly ?string $subject = null,
    ) {
        if (!preg_match(self::SCOPE_PATTERN, $scope)) {
            throw new BrokerRejected(BrokerRejected::INVALID_REQUEST, 'Scope is not a valid scope name.');
        }
        if (!preg_match(self::CALLER_PATTERN, $caller)) {
            throw new BrokerRejected(BrokerRejected::INVALID_REQUEST, 'Caller is not a valid component name.');
        }
        if ($subject !== null && !preg_match(self::SUBJECT_PATTERN, $subject)) {
            throw new BrokerRejected(BrokerRejected::INVALID_REQUEST, 'Subject is not a valid identifier.');
        }
    }

    /** @return array{provider: string, scope: string, caller: string, user_id: int, subject: ?string} */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'scope'    => $this->scope,
            'caller'   => $this->caller,
            'user_id'  => $this->userId,
            'subject'  => $this->subject,
        ];
    }

    /** @param array<string, mixed> $record */
    public function matches(array $record): bool
    {
        return ($record['provider'] ?? null) === $this->provider
            && ($record['scope'] ?? null) === $this->scope
            && ($record['caller'] ?? null) === $this->caller
            && ($record['user_id'] ?? null) === $this->userId
            && ($record['subject'] ?? null) === $this->subject;
    }
}
