<?php

namespace App\Support;

final class CustomerStatus
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const EXPIRED = 'expired';

    public const TERMINATED = 'terminated';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::EXPIRED => 'Expired',
            self::TERMINATED => 'Terminated',
        ];
    }

    /** @return array<string, string> */
    public static function colors(): array
    {
        return [
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::EXPIRED => 'danger',
            self::TERMINATED => 'gray',
        ];
    }

    public static function label(string $status): string
    {
        return self::options()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function color(string $status): string
    {
        return self::colors()[$status] ?? 'gray';
    }

    /** Statuses that block portal login and should suspend network when enforced. */
    public static function isRestricted(string $status): bool
    {
        return in_array($status, [self::SUSPENDED, self::EXPIRED, self::TERMINATED], true);
    }

    /** Legacy alias kept for coordinator / imports. */
    public static function normalize(string $status): string
    {
        return $status === 'inactive' ? self::EXPIRED : $status;
    }
}
