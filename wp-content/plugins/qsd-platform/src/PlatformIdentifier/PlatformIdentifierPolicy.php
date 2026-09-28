<?php

declare(strict_types=1);

namespace QSD\Platform\PlatformIdentifier;

/**
 * Closed vocabulary and format policy for permanent Platform identifiers.
 *
 * Extending identity support means adding one entry here (a new QSD-prefixed
 * code per Station) and then wiring the owning domain in its own
 * implementation phase. Prefixes are permanent once records exist. The engine itself must not
 * branch on domain storage or behaviour.
 */
final class PlatformIdentifierPolicy
{
    public const SERVICE  = 'service';
    public const CATEGORY = 'category';

    public const ALPHABET    = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
    public const SUFFIX_LENGTH = 5;

    /** @var array<string, string> */
    private const PREFIXES = [
        self::SERVICE  => 'QSDS',
        self::CATEGORY => 'QSDC',
    ];

    /** @return array<string, string> */
    public static function prefixes(): array
    {
        return self::PREFIXES;
    }

    public static function supports(string $entityType): bool
    {
        return isset(self::PREFIXES[$entityType]);
    }

    public static function prefix(string $entityType): string
    {
        if (!self::supports($entityType)) {
            throw PlatformIdentifierConflict::unsupportedEntityType($entityType);
        }

        return self::PREFIXES[$entityType];
    }

    public static function validate(string $entityType, string $platformId): bool
    {
        if (!self::supports($entityType)) {
            return false;
        }

        $prefix = preg_quote(self::PREFIXES[$entityType], '/');

        return preg_match('/^' . $prefix . '[2-9A-HJKMNP-TV-Z]{' . self::SUFFIX_LENGTH . '}$/D', $platformId) === 1;
    }

    public static function entityTypeFor(string $platformId): ?string
    {
        foreach (self::PREFIXES as $entityType => $prefix) {
            if (self::validate($entityType, $platformId)) {
                return $entityType;
            }
        }

        return null;
    }
}
