<?php

namespace App\Support;

final class BdPhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && $digits[0] === '1') {
            $digits = '0'.$digits;
        }

        return strlen($digits) >= 11 ? $digits : null;
    }

    /**
     * @return list<string>
     */
    public static function variants(?string $phone): array
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return [];
        }

        $variants = [$normalized];
        if (str_starts_with($normalized, '0')) {
            $variants[] = substr($normalized, 1);
            $variants[] = '880'.substr($normalized, 1);
        }

        return array_values(array_unique($variants));
    }
}
