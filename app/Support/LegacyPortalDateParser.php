<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Parse legacy portal (pay.anetbd.com) date strings including .NET /Date(ms)/ JSON.
 */
final class LegacyPortalDateParser
{
    private const FORMATS = [
        'd-M-Y',
        'd-M-y',
        'd/m/Y',
        'd/m/y',
        'd-m-Y',
        'd-m-y',
        'M-d-Y',
        'M-d-y',
        'd M Y',
        'd M y',
        'M d, Y',
        'M d Y',
        'Y-m-d',
        'Y/m/d',
        'Y-m-d H:i:s',
        'd/m/Y H:i:s',
        'd-M-Y H:i:s',
    ];

    public static function parse(mixed $value, bool $startOfDay = false): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $startOfDay ? $value->copy()->startOfDay() : $value->copy();
        }

        $dotnet = self::parseDotNetDate($value);
        if ($dotnet !== null) {
            return $startOfDay ? $dotnet->startOfDay() : $dotnet;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                return $startOfDay ? $parsed->startOfDay() : $parsed;
            } catch (\Throwable) {
                continue;
            }
        }

        if (preg_match('/^([A-Za-z]{3,})-(\d{2})$/i', $value, $m)) {
            try {
                $parsed = Carbon::parse('1 '.$m[1].' '.(2000 + (int) $m[2]));

                return $startOfDay ? $parsed->startOfMonth() : $parsed;
            } catch (\Throwable) {
                // fall through
            }
        }

        try {
            $parsed = Carbon::parse($value);

            return $startOfDay ? $parsed->startOfDay() : $parsed;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseBillMonth(mixed $value): ?Carbon
    {
        $parsed = self::parse($value, true);

        return $parsed?->startOfMonth();
    }

    public static function parseDotNetDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/\/Date\((\d+)\)\//', $value, $m)) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $m[1]);
    }
}
