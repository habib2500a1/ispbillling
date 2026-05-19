<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class IspTimezone
{
    /**
     * @return array<string, string>
     */
    public static function optionsForSelect(): array
    {
        return [
            'Asia/Dhaka' => 'Bangladesh — BDT (UTC+6)',
            'Asia/Kolkata' => 'India — IST (UTC+5:30)',
            'Asia/Karachi' => 'Pakistan — PKT (UTC+5)',
            'Asia/Dubai' => 'UAE — GST (UTC+4)',
            'Asia/Singapore' => 'Singapore — SGT (UTC+8)',
            'Asia/Bangkok' => 'Thailand — ICT (UTC+7)',
            'Europe/London' => 'UK — GMT/BST',
            'UTC' => 'UTC',
            'America/New_York' => 'US Eastern',
        ];
    }

    public static function isValidZone(string $zone): bool
    {
        return in_array($zone, timezone_identifiers_list(), true);
    }

    public static function zone(): string
    {
        return (string) config('app.timezone', 'Asia/Dhaka');
    }

    public static function label(): string
    {
        return (string) config('isp.timezone_label', 'BDT');
    }

    public static function description(): string
    {
        $zone = self::zone();

        try {
            $offset = now($zone)->format('P');
        } catch (\Throwable) {
            $offset = '+06:00';
        }

        return sprintf('%s (%s, UTC%s)', self::label(), str_replace('_', ' ', $zone), $offset);
    }

    public static function nowFormatted(string $format = 'Y-m-d H:i:s'): string
    {
        return now(self::zone())->format($format).' '.self::label();
    }

    public static function format(?CarbonInterface $at, string $format = 'Y-m-d H:i'): ?string
    {
        if ($at === null) {
            return null;
        }

        return $at->timezone(self::zone())->format($format).' '.self::label();
    }
}
