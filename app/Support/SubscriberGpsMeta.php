<?php

namespace App\Support;

final class SubscriberGpsMeta
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function normalize(array $meta, array $existing = []): array
    {
        $lat = self::parseCoordinate($meta['gps_lat'] ?? null);
        $lng = self::parseCoordinate($meta['gps_lng'] ?? null);

        if (($lat === null || $lng === null) && filled($meta['gps_combined'] ?? null)) {
            $parsed = self::parseCombined((string) $meta['gps_combined']);
            $lat = $lat ?? $parsed['lat'];
            $lng = $lng ?? $parsed['lng'];
        }

        unset($meta['gps_combined']);

        if ($lat === null && $lng === null) {
            unset($meta['gps_lat'], $meta['gps_lng']);

            return $meta;
        }

        if ($lat === null || $lng === null) {
            $lat = $lat ?? self::parseCoordinate($existing['gps_lat'] ?? null);
            $lng = $lng ?? self::parseCoordinate($existing['gps_lng'] ?? null);

            if ($lat === null || $lng === null) {
                unset($meta['gps_lat'], $meta['gps_lng']);

                return $meta;
            }
        }

        $meta['gps_lat'] = number_format($lat, 7, '.', '');
        $meta['gps_lng'] = number_format($lng, 7, '.', '');

        return $meta;
    }

    /**
     * @return array{lat: ?float, lng: ?float}
     */
    public static function parseCombined(string $value): array
    {
        $parts = preg_split('/[,\s]+/', trim($value)) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if (count($parts) < 2) {
            return ['lat' => null, 'lng' => null];
        }

        $lat = self::parseCoordinate($parts[0]);
        $lng = self::parseCoordinate($parts[1]);

        return ['lat' => $lat, 'lng' => $lng];
    }

    public static function parseCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
