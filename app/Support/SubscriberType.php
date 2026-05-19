<?php

namespace App\Support;

final class SubscriberType
{
    public const STANDARD = 'standard';

    public const FREE = 'free';

    public const VIP = 'vip';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::STANDARD => 'Standard (bill + auto on/off)',
            self::FREE => 'Free / complimentary (no bill, line stays on)',
            self::VIP => 'VIP (bill ok, no auto line off)',
        ];
    }

    /** @return array<string, string> */
    public static function colors(): array
    {
        return [
            self::STANDARD => 'gray',
            self::FREE => 'info',
            self::VIP => 'warning',
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? ucfirst($type);
    }

    public static function color(string $type): string
    {
        return self::colors()[$type] ?? 'gray';
    }

    public static function normalize(string $type): string
    {
        return match (strtolower(trim($type))) {
            self::FREE, 'complimentary', 'gratis' => self::FREE,
            self::VIP => self::VIP,
            default => self::STANDARD,
        };
    }

    public static function skipsBilling(string $type): bool
    {
        return self::normalize($type) === self::FREE;
    }

    public static function isExemptFromAutoSuspend(string $type): bool
    {
        return in_array(self::normalize($type), [self::FREE, self::VIP], true);
    }
}
