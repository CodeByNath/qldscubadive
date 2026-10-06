<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * CredentialCipher — the AEAD primitive for provider secrets and sealed data
 * keys. It holds raw key material only in memory; CredentialKeyring decides
 * which keys it gets and where they come from.
 *
 * Algorithm: libsodium XChaCha20-Poly1305 (IETF AEAD), a fresh random nonce
 * per seal. The additional data binds every ciphertext to its context
 * (provider and field, or a keyring slot), so a sealed value cannot be moved
 * to another slot. Each envelope records a key id (an HMAC fingerprint, never
 * the key) that selects the key able to open it, so a value sealed under any
 * other key is detected rather than mis-decrypted.
 *
 * A cipher seals under one key and may open under several (earlier keyring
 * generations). Fail closed: with no sealing key `seal()` refuses, and with no
 * matching key `open()` returns null — nothing is ever stored or read as
 * plaintext, and there is no reversible obfuscation fallback.
 */
final class CredentialCipher
{
    public const ALGORITHM = 'xchacha20poly1305-ietf';
    public const KEY_BYTES = 32;

    // Literal size rather than the SODIUM_* constant, so a runtime without
    // libsodium reads as "unavailable" instead of failing to load.
    private const NONCE_BYTES = 24;

    private ?string $key = null;

    /** @var array<string, string> key id => raw key, every key this cipher opens with */
    private array $openers = [];

    /**
     * @param string|null  $key      raw 32-byte sealing key, or null when none is usable
     * @param list<string> $openKeys further raw keys accepted for opening only
     */
    public function __construct(#[\SensitiveParameter] ?string $key, #[\SensitiveParameter] array $openKeys = [])
    {
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return;
        }
        foreach ([$key, ...$openKeys] as $candidate) {
            if (is_string($candidate) && strlen($candidate) === self::KEY_BYTES) {
                $this->openers[self::idOf($candidate)] = $candidate;
            }
        }
        $this->key = is_string($key) && strlen($key) === self::KEY_BYTES ? $key : null;
    }

    /** The key-id fingerprint of a raw key. Never the key itself. */
    public static function idOf(#[\SensitiveParameter] string $key): string
    {
        return substr(hash_hmac('sha256', 'qsd-credential-key-id', $key), 0, 16);
    }

    /** This cipher can seal (it has a valid key and libsodium is present). */
    public function isAvailable(): bool
    {
        return $this->key !== null;
    }

    public function keyId(): ?string
    {
        return $this->key === null ? null : self::idOf($this->key);
    }

    /** @return list<string> the ids of every key this cipher opens with — for leak guards only, never projected */
    public function keyIds(): array
    {
        return array_map('strval', array_keys($this->openers));
    }

    /** @return array{v: int, alg: string, kid: string, nonce: string, ct: string} */
    public function seal(#[\SensitiveParameter] string $plaintext, string $context): array
    {
        if ($this->key === null) {
            throw new CredentialCipherUnavailable();
        }
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $this->aad($context), $nonce, $this->key);

        return [
            'v'     => 1,
            'alg'   => self::ALGORITHM,
            'kid'   => (string) $this->keyId(),
            'nonce' => base64_encode($nonce),
            'ct'    => base64_encode($ciphertext),
        ];
    }

    /** The plaintext, or null for no matching key, tampering, or a non-envelope value. */
    public function open(mixed $envelope, string $context): ?string
    {
        if (!is_array($envelope) || ($envelope['v'] ?? null) !== 1 || ($envelope['alg'] ?? null) !== self::ALGORITHM) {
            return null;
        }
        $key = is_string($envelope['kid'] ?? null) ? ($this->openers[$envelope['kid']] ?? null) : null;
        if ($key === null) {
            return null;
        }
        $nonce = base64_decode((string) ($envelope['nonce'] ?? ''), true);
        $ciphertext = base64_decode((string) ($envelope['ct'] ?? ''), true);
        if ($nonce === false || $ciphertext === false || strlen($nonce) !== self::NONCE_BYTES) {
            return null;
        }
        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $this->aad($context), $nonce, $key);
        } catch (\SodiumException) {
            return null;
        }
        return $plaintext === false ? null : $plaintext;
    }

    /** The additional-data context for one provider secret field. */
    public static function context(string $provider, string $field): string
    {
        return "{$provider}:{$field}";
    }

    private function aad(string $context): string
    {
        return 'qsd-credential:v1:' . $context;
    }
}
