<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * The decrypted credential handle the broker gives a Settings-owned provider
 * operation for the duration of one consumed request — and nobody else.
 * It refuses serialisation and hides its contents from debug output, so a
 * secret cannot leak through logging or caching by accident.
 */
final class ProviderSecrets
{
    /** @param \Closure(string): ?string $reader */
    public function __construct(public readonly string $provider, private \Closure $reader) {}

    public function get(string $field): ?string
    {
        return ($this->reader)($field);
    }

    public function __debugInfo(): array
    {
        return ['provider' => $this->provider, 'secrets' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Provider secrets cannot be serialised.');
    }
}
