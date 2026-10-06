<?php

/*
 * FILE INDEX
 *
 * KEYRING_SOURCES   Wrapping keys derived from server configuration
 * KEYRING_USE       The cipher for opening and for sealing (first-save bootstrap)
 * KEYRING_ROTATION  Stage a new generation, retire the old ones
 * KEYRING_STORAGE   The sealed-generation option
 *
 * Search: SECTION: KEYRING_SOURCES ... SECTION: KEYRING_STORAGE
 */

namespace QSD\Platform\Modules\Settings\Security;

/**
 * CredentialKeyring — QSD's own credential data key, so an administrator can
 * save provider API keys with no server setup.
 *
 * Hierarchy: provider secret → sealed under a random 32-byte data key → the
 * data key sealed under a wrapping key. The wrapping key is never stored: it is
 * HKDF-SHA256 (context `qsd-credential-wrap:v1`) over the site's WordPress
 * secret keys `SECURE_AUTH_KEY` + `SECURE_AUTH_SALT`, which WordPress keeps in
 * its server configuration, outside the database. When the server also defines
 * `QSD_CREDENTIAL_KEY` (optional hosting hardening), that is derived the same
 * way and preferred for new wraps. The database alone never holds enough to
 * open a credential.
 *
 * The first secret save generates the data key. Each generation is stored
 * sealed, bound to `keyring:v1:<key id>`, in one non-autoloaded option. If no
 * wrapping key can open the active generation (the WordPress secret keys were
 * regenerated), the next save starts a new generation and keeps the old sealed
 * one: affected API keys read as not set until an administrator re-enters
 * them, and nothing is deleted.
 *
 * Nothing here ever returns a raw key, a wrapped key or a wrapping key outside
 * a CredentialCipher. Key ids are fingerprints for selection and leak guards.
 */
final class CredentialKeyring
{
    public const OPTION            = 'qsd_settings_credential_keyring';
    public const HARDENING_CONSTANT = 'QSD_CREDENTIAL_KEY';
    public const WRAP_CONTEXT      = 'qsd-credential-wrap:v1';

    private const SITE_CONSTANTS = ['SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'];
    private const PLACEHOLDER    = 'put your unique phrase here';
    private const MIN_SECRET     = 32;

    /** @var list<CredentialCipher> usable wrapping ciphers, preferred first */
    private array $wrappers;

    /**
     * @param list<CredentialCipher> $wrappers   wrapping ciphers, preferred first; unusable ones are ignored
     * @param list<string>           $legacyKeys raw keys that sealed secrets directly before the keyring existed
     */
    public function __construct(array $wrappers, #[\SensitiveParameter] private array $legacyKeys = [])
    {
        $this->wrappers = array_values(array_filter($wrappers, static fn(CredentialCipher $w) => $w->isAvailable()));
    }

    // =======================================================================
    // SECTION: KEYRING_SOURCES
    // =======================================================================

    /** The keyring for this install, wrapped by keys derived from its server configuration. */
    public static function fromEnvironment(): self
    {
        $wrappers = [];
        $legacy = [];
        $hardening = defined(self::HARDENING_CONSTANT) ? base64_decode((string) constant(self::HARDENING_CONSTANT), true) : false;
        if (is_string($hardening) && strlen($hardening) === CredentialCipher::KEY_BYTES) {
            $wrappers[] = new CredentialCipher(self::derive($hardening));
            $legacy[] = $hardening;
        }
        $site = self::siteSecret();
        if ($site !== null) {
            $wrappers[] = new CredentialCipher(self::derive($site));
        }
        return new self($wrappers, $legacy);
    }

    /** A wrapping key from input key material, with QSD-specific context separation. */
    public static function derive(#[\SensitiveParameter] string $material): string
    {
        return hash_hkdf('sha256', $material, CredentialCipher::KEY_BYTES, self::WRAP_CONTEXT, 'qsd-platform');
    }

    /** The WordPress secret keys, or null when absent, too short, or the sample placeholder. */
    private static function siteSecret(): ?string
    {
        $parts = [];
        foreach (self::SITE_CONSTANTS as $constant) {
            $value = defined($constant) ? constant($constant) : null;
            if (!is_string($value) || strlen($value) < self::MIN_SECRET || str_contains($value, self::PLACEHOLDER)) {
                return null;
            }
            $parts[] = $value;
        }
        return implode("\0", $parts);
    }

    // =======================================================================
    // SECTION: KEYRING_USE
    // =======================================================================

    /** Secure storage can seal on this install (a wrapping key and libsodium are present). */
    public function isAvailable(): bool
    {
        return $this->wrappers !== [];
    }

    /**
     * Read-only: seals under the active generation when it can be opened, and
     * opens under every generation that can (plus legacy keys). Writes nothing.
     */
    public function cipher(): CredentialCipher
    {
        $ring = $this->read();
        $keys = $this->unwrapAll($ring);
        return new CredentialCipher($keys[$ring['active']] ?? null, [...array_values($keys), ...$this->legacyKeys]);
    }

    /**
     * The cipher for a secret save. The first save, or a save after the active
     * generation became unopenable, creates a new generation first.
     */
    public function sealingCipher(): CredentialCipher
    {
        if (!$this->isAvailable()) {
            throw new CredentialCipherUnavailable();
        }
        $cipher = $this->cipher();
        if ($cipher->isAvailable()) {
            return $cipher;
        }
        $this->stage(random_bytes(CredentialCipher::KEY_BYTES));
        return $this->cipher();
    }

    /** @return array{generations: int, openable: int} counts only */
    public function status(): array
    {
        $ring = $this->read();
        return ['generations' => count($ring['keys']), 'openable' => count($this->unwrapAll($ring))];
    }

    // =======================================================================
    // SECTION: KEYRING_ROTATION
    // =======================================================================

    /** Adds a generation and makes it active; earlier generations are kept. */
    public function stage(#[\SensitiveParameter] string $key): void
    {
        if (!$this->isAvailable()) {
            throw new CredentialCipherUnavailable();
        }
        $kid = CredentialCipher::idOf($key);
        $ring = $this->read();
        $ring['keys'][$kid] = $this->wrappers[0]->seal($key, self::slot($kid));
        $ring['active'] = $kid;
        $this->write($ring);
    }

    /**
     * Keeps only the active generation, re-sealed under the preferred wrapping
     * key. Called after every secret has moved to it.
     */
    public function retireInactive(): void
    {
        $ring = $this->read();
        $key = $this->unwrapAll($ring)[$ring['active']] ?? null;
        if ($key === null) {
            throw new CredentialCipherUnavailable();
        }
        $this->write(['active' => $ring['active'], 'keys' => [$ring['active'] => $this->wrappers[0]->seal($key, self::slot($ring['active']))]]);
    }

    // =======================================================================
    // SECTION: KEYRING_STORAGE
    // =======================================================================

    /** @return array<string, string> key id => raw data key, for every generation a wrapping key opens */
    private function unwrapAll(array $ring): array
    {
        $keys = [];
        foreach ($ring['keys'] as $kid => $sealed) {
            foreach ($this->wrappers as $wrapper) {
                $key = $wrapper->open($sealed, self::slot((string) $kid));
                if ($key !== null && CredentialCipher::idOf($key) === $kid) {
                    $keys[(string) $kid] = $key;
                    break;
                }
            }
        }
        return $keys;
    }

    private static function slot(string $kid): string
    {
        return "keyring:v1:{$kid}";
    }

    /** @return array{active: string, keys: array<string, mixed>} */
    private function read(): array
    {
        $value = get_option(self::OPTION, []);
        $value = is_array($value) ? $value : [];
        return [
            'active' => is_string($value['active'] ?? null) ? $value['active'] : '',
            'keys'   => is_array($value['keys'] ?? null) ? $value['keys'] : [],
        ];
    }

    /** @param array{active: string, keys: array<string, mixed>} $ring */
    private function write(array $ring): void
    {
        $value = ['v' => 1] + $ring;
        if (!add_option(self::OPTION, $value, '', 'no')) {
            update_option(self::OPTION, $value, false);
        }
    }
}
