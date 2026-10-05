<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * CredentialCipher — at-rest encryption for provider secrets.
 *
 * Key material lives OUTSIDE the database, in the `QSD_CREDENTIAL_KEY`
 * wp-config.php constant — the same rule MailService already follows for SMTP
 * credentials. The constant is a base64-encoded 32-byte random key, e.g.
 *
 *     define('QSD_CREDENTIAL_KEY', '<output of: php -r "echo base64_encode(random_bytes(32));">');
 *
 * Algorithm: libsodium XChaCha20-Poly1305 (IETF AEAD), a fresh random nonce
 * per seal. The additional data binds every ciphertext to its provider and
 * field, so a sealed value cannot be moved to another slot. Each envelope
 * records a key id (an HMAC fingerprint, never the key) so a value sealed
 * under a different key is detected rather than mis-decrypted.
 *
 * Fail closed: with no valid key, `seal()` refuses and `open()` returns null —
 * nothing is ever stored or read as plaintext, and there is no reversible
 * obfuscation fallback.
 */
final class CredentialCipher
{
    public const CONSTANT = 'QSD_CREDENTIAL_KEY';
    public const ALGORITHM = 'xchacha20poly1305-ietf';

    // Literal sizes rather than the SODIUM_* constants, so a runtime without
    // libsodium reads as "unavailable" instead of failing to load.
    private const KEY_BYTES   = 32;
    private const NONCE_BYTES = 24;

    private ?string $key;

    /** @param string|null $key raw 32-byte key, or null when none is configured */
    public function __construct(?string $key)
    {
        $usable = $key !== null
            && strlen($key) === self::KEY_BYTES
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
        $this->key = $usable ? $key : null;
    }

    public static function fromEnvironment(): self
    {
        if (!defined(self::CONSTANT)) {
            return new self(null);
        }
        $decoded = base64_decode((string) constant(self::CONSTANT), true);
        return new self($decoded === false ? null : $decoded);
    }

    public function isAvailable(): bool
    {
        return $this->key !== null;
    }

    public function keyId(): ?string
    {
        return $this->key === null ? null : substr(hash_hmac('sha256', 'qsd-credential-key-id', $this->key), 0, 16);
    }

    /** @return array{v: int, alg: string, kid: string, nonce: string, ct: string} */
    public function seal(string $plaintext, string $context): array
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

    /** The plaintext, or null for no key, a foreign key, tampering, or a non-envelope value. */
    public function open(mixed $envelope, string $context): ?string
    {
        if ($this->key === null || !is_array($envelope)) {
            return null;
        }
        if (($envelope['v'] ?? null) !== 1 || ($envelope['alg'] ?? null) !== self::ALGORITHM || ($envelope['kid'] ?? null) !== $this->keyId()) {
            return null;
        }
        $nonce = base64_decode((string) ($envelope['nonce'] ?? ''), true);
        $ciphertext = base64_decode((string) ($envelope['ct'] ?? ''), true);
        if ($nonce === false || $ciphertext === false || strlen($nonce) !== self::NONCE_BYTES) {
            return null;
        }
        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $this->aad($context), $nonce, $this->key);
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
