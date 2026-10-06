<?php

namespace QSD\Platform\Modules\Settings\Security;

/**
 * WpdbCredentialMutationGuard — the credential guard as one row in the
 * WordPress options table, taken and released through `$wpdb` directly.
 *
 * Why not the options API: `add_option()` checks through the object cache and
 * then upserts, so two requests can both believe they hold it. A plain
 * `$wpdb->insert()` hits the options table's unique key: exactly one request
 * inserts the row. Release deletes the row only when it still carries this
 * holder's token. A row left by a crashed request expires after LEASE seconds
 * and is broken with a delete that must match its exact value, so two waiters
 * cannot both break it. The row holds a random token and an expiry only.
 */
final class WpdbCredentialMutationGuard implements CredentialMutationGuard
{
    public const ROW   = 'qsd_settings_credential_guard';
    public const LEASE = 60;
    public const WAIT  = 10;

    /** @var \Closure(): int */
    private \Closure $clock;

    /** @var \Closure(): void */
    private \Closure $pause;

    /**
     * @param (\Closure(): int)|null  $clock
     * @param (\Closure(): void)|null $pause called between attempts
     */
    public function __construct(?\Closure $clock = null, ?\Closure $pause = null, private int $wait = self::WAIT)
    {
        $this->clock = $clock ?? static fn(): int => time();
        $this->pause = $pause ?? static function (): void { usleep(100_000); };
    }

    public function hold(\Closure $critical): mixed
    {
        $token = $this->acquire();
        try {
            return $critical();
        } finally {
            $this->release($token);
        }
    }

    private function acquire(): string
    {
        global $wpdb;
        $token = bin2hex(random_bytes(16));
        $deadline = ($this->clock)() + $this->wait;
        while (true) {
            $value = (string) json_encode(['token' => $token, 'expires_at' => ($this->clock)() + self::LEASE]);
            if ($wpdb->insert($wpdb->options, ['option_name' => self::ROW, 'option_value' => $value, 'autoload' => 'no']) === 1) {
                return $value;
            }
            $held = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::ROW));
            $record = is_string($held) ? json_decode($held, true) : null;
            if (is_string($held) && (!is_array($record) || (int) ($record['expires_at'] ?? 0) < ($this->clock)())) {
                // An abandoned lease: break it only if it is still that exact row.
                $wpdb->delete($wpdb->options, ['option_name' => self::ROW, 'option_value' => $held]);
                continue;
            }
            if (($this->clock)() >= $deadline) {
                throw new CredentialMutationBusy();
            }
            ($this->pause)();
        }
    }

    private function release(string $value): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->options, ['option_name' => self::ROW, 'option_value' => $value]);
    }
}
