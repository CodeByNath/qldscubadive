<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * What issuance hands back to the in-process caller, once. `key` is a
 * security artifact — never a Platform ID, never persisted, never logged.
 * `requestId` is the safe audit handle.
 */
final class IssuedRequestKey
{
    public function __construct(
        public readonly string $requestId,
        #[\SensitiveParameter] public readonly string $key,
        public readonly int $expiresAt,
    ) {}

    public function __debugInfo(): array
    {
        return ['requestId' => $this->requestId, 'key' => '[redacted]', 'expiresAt' => $this->expiresAt];
    }
}
