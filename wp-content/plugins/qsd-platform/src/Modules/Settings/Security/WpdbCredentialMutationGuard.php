<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * WpdbCredentialMutationGuard — the credential guard as a database named lock
 * (`GET_LOCK`) on the WordPress database connection.
 *
 * Why a named lock: the lock belongs to the holder's database connection. It
 * never expires while the holder runs, however long that takes, and the
 * server frees it the moment that connection ends (a crashed or killed
 * request), so recovery needs no lease or clock.
 *
 * A holder that loses its connection must not resume on a new one without the
 * lock. While held, `$wpdb` reconnection is switched off (a lost connection
 * ends the request instead), and `assertHeld()` proves before each credential
 * write that this same connection still owns the lock.
 *
 * Not re-entrant: MySQL lets one connection take a lock it already holds, so
 * the guard refuses when this connection is already the owner.
 */
final class WpdbCredentialMutationGuard implements CredentialMutationGuard
{
    public const WAIT = 10;

    /** The connection id that holds the lock, while `hold()` runs. */
    private ?string $connection = null;

    public function __construct(private int $wait = self::WAIT)
    {
    }

    public function hold(\Closure $critical): mixed
    {
        global $wpdb;
        $name = self::lockName();
        $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
        if ($this->connection !== null || $this->owner($name) === $connection) {
            throw new CredentialMutationBusy();
        }
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, $this->wait)) !== '1') {
            throw new CredentialMutationBusy();
        }

        $retries = $wpdb->reconnect_retries;
        $wpdb->reconnect_retries = 0;
        $this->connection = $connection;
        try {
            return $critical();
        } finally {
            $this->connection = null;
            $wpdb->reconnect_retries = $retries;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }

    public function assertHeld(): void
    {
        global $wpdb;
        if (
            $this->connection === null
            || (string) $wpdb->get_var('SELECT CONNECTION_ID()') !== $this->connection
            || $this->owner(self::lockName()) !== $this->connection
        ) {
            throw new CredentialMutationLost();
        }
    }

    /** Named locks are server-wide: the name is scoped to this site's database and table prefix. */
    public static function lockName(): string
    {
        global $wpdb;
        $site = (defined('DB_NAME') ? DB_NAME : '') . '|' . $wpdb->options;
        return 'qsd_credential_guard_' . substr(hash('sha256', $site), 0, 16);
    }

    private function owner(string $name): ?string
    {
        global $wpdb;
        $owner = $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $name));
        return $owner === null ? null : (string) $owner;
    }
}
