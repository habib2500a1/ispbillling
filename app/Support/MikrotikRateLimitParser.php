<?php

namespace App\Support;

/**
 * Best-effort parse of RouterOS PPP profile / queue style rate-limit strings for UI + billing package Mbps.
 *
 * Examples: "10M/10M", "512k/1m", "0/0", "1000000/1000000" (bits/s).
 */
final class MikrotikRateLimitParser
{
    /**
     * @return array{down_mbps: int|null, up_mbps: int|null, bandwidth_label: string|null}
     */
    public static function parse(?string $rateLimit): array
    {
        if ($rateLimit === null) {
            return ['down_mbps' => null, 'up_mbps' => null, 'bandwidth_label' => null];
        }

        $rateLimit = trim($rateLimit);
        if ($rateLimit === '' || $rateLimit === '0/0') {
            return ['down_mbps' => null, 'up_mbps' => null, 'bandwidth_label' => 'Unlimited'];
        }

        $parts = explode('/', $rateLimit, 2);
        if (count($parts) !== 2) {
            return ['down_mbps' => null, 'up_mbps' => null, 'bandwidth_label' => $rateLimit];
        }

        $down = self::partToMbpsInt(trim($parts[0]));
        $up = self::partToMbpsInt(trim($parts[1]));

        $label = self::formatBandwidthLabel($down, $up, $rateLimit);

        return [
            'down_mbps' => $down,
            'up_mbps' => $up,
            'bandwidth_label' => $label,
        ];
    }

    private static function partToMbpsInt(string $part): ?int
    {
        if ($part === '' || $part === '0') {
            return null;
        }

        if (preg_match('/^([0-9.]+)\s*([kKmMgG]?)$/', $part, $m)) {
            $n = (float) $m[1];
            $suffix = strtoupper($m[2] ?? '');
            $bps = match ($suffix) {
                'K' => $n * 1_000,
                'M' => $n * 1_000_000,
                'G' => $n * 1_000_000_000,
                default => $n,
            };

            return max(0, (int) round($bps / 1_000_000));
        }

        if (preg_match('/^[0-9]+$/', $part)) {
            $bps = (float) $part;

            return max(0, (int) round($bps / 1_000_000));
        }

        return null;
    }

    private static function formatBandwidthLabel(?int $down, ?int $up, string $raw): string
    {
        if ($down === null && $up === null) {
            return $raw;
        }

        if ($down !== null && $up !== null && $down === $up) {
            return $down.' Mbps symmetric';
        }

        $d = $down !== null ? (string) $down.'↓' : '?↓';
        $u = $up !== null ? (string) $up.'↑' : '?↑';

        return trim($d.' / '.$u.' Mbps');
    }
}
