<?php

namespace App\Services\Optical\Normalization;

/**
 * Vendor-agnostic optical power normalization (OLT-native → dBm).
 */
final class OpticalPowerNormalizer
{
    /**
     * @param  mixed  $raw  SNMP/CLI raw value
     */
    public function normalizeRx(mixed $raw, ?string $vendorProfile = null): ?float
    {
        return $this->normalize($raw, $vendorProfile, 'rx');
    }

    /**
     * @param  mixed  $raw
     */
    public function normalizeTx(mixed $raw, ?string $vendorProfile = null): ?float
    {
        return $this->normalize($raw, $vendorProfile, 'tx');
    }

    /**
     * @param  mixed  $raw
     */
    public function normalize(mixed $raw, ?string $vendorProfile, string $metric = 'rx'): ?float
    {
        if ($raw === null || $raw === '' || $raw === 'N/A') {
            return null;
        }

        $profile = $this->resolveProfile($vendorProfile);
        $numeric = $this->toNumeric($raw);
        if ($numeric === null) {
            return null;
        }

        $dbm = match ($profile['mode'] ?? 'auto') {
            'tenth_dbm' => $numeric / (float) ($profile['divisor'] ?? 10),
            'centi_dbm' => $numeric / (float) ($profile['divisor'] ?? 100),
            'milli_dbm' => $numeric / 1000,
            'offset_dbm' => $numeric + (float) ($profile['offset'] ?? 0),
            'auto' => $this->autoDetect($numeric),
            default => $numeric,
        };

        if ($dbm === null) {
            return null;
        }

        $min = (float) ($profile['min_dbm'] ?? -60);
        $max = (float) ($profile['max_dbm'] ?? 10);

        if ($dbm < $min || $dbm > $max) {
            return null;
        }

        return round($dbm, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfile(?string $vendorProfile): array
    {
        if ($vendorProfile === null || $vendorProfile === '') {
            return config('optical.normalization.default', ['mode' => 'auto']);
        }

        $profiles = config('optical.normalization.profiles', []);

        return $profiles[$vendorProfile]
            ?? $profiles[config('gpon.driver_to_profile.'.$vendorProfile, '')] ?? []
            ?: config('optical.normalization.default', ['mode' => 'auto']);
    }

    private function autoDetect(float $numeric): ?float
    {
        $abs = abs($numeric);

        if ($abs === 0.0) {
            return null;
        }

        if ($numeric < 0 && $abs <= 50) {
            return round($numeric, 3);
        }

        if ($abs >= 1000 && $abs <= 40000) {
            return round($numeric / 10, 3);
        }

        if ($abs >= 100 && $abs <= 4000) {
            return round($numeric / 10, 3);
        }

        if ($abs <= 50) {
            return round($numeric, 3);
        }

        return null;
    }

    private function toNumeric(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $text = trim((string) $raw);
        $text = preg_replace('/^[A-Za-z-]+:\s*/', '', $text) ?? $text;
        $text = trim($text, "\" \t");

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*dBm$/i', $text, $m)) {
            return (float) $m[1];
        }

        if (is_numeric($text)) {
            return (float) $text;
        }

        return null;
    }
}
