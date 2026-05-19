<?php

namespace App\Support;

final class MacAddress
{
    public static function normalizeCompact(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $compact = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $value) ?? '');

        return strlen($compact) >= 12 ? substr($compact, 0, 12) : null;
    }

    public static function normalizeColon(?string $value): ?string
    {
        $compact = self::normalizeCompact($value);
        if ($compact === null) {
            return null;
        }

        return implode(':', str_split($compact, 2));
    }

    /**
     * @return list<string>
     */
    public static function variants(?string $value): array
    {
        $compact = self::normalizeCompact($value);
        if ($compact === null) {
            return [];
        }

        $colon = self::normalizeColon($compact);

        return array_values(array_unique(array_filter([
            $compact,
            $colon,
            strtolower($colon ?? ''),
        ])));
    }

    /**
     * Apply MAC match on devices.mac_address and serial_number (colon / compact / case).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Device>  $query
     */
    public static function applyOnuMacMatch($query, string $mac): void
    {
        $compact = self::normalizeCompact($mac);
        if ($compact === null) {
            return;
        }

        $variants = self::variants($compact);

        $query->where(function ($q) use ($variants, $compact): void {
            foreach ($variants as $variant) {
                $q->orWhere('mac_address', $variant);
            }
            $q->orWhere('serial_number', $compact)
                ->orWhereRaw("REPLACE(UPPER(COALESCE(mac_address, '')), ':', '') = ?", [$compact]);
        });
    }

    /**
     * @return list<string> 12-hex compact MACs
     */
    public static function parseMacInputs(?string ...$values): array
    {
        $out = [];
        foreach ($values as $value) {
            $compact = self::normalizeCompact($value);
            if ($compact !== null) {
                $out[$compact] = $compact;
            }
        }

        return array_values($out);
    }

    /**
     * Parse MAC from net-snmp / PHP ext-snmp values (Hex-STRING, plain octets, colon/compact).
     */
    public static function fromSnmpValue(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // net-snmp SNMP_VALUE_PLAIN: BDCOM onuMac is exactly 6 octets (do not trim \0 — leading zero is valid).
        if (strlen($raw) === 6) {
            return self::normalizeColon(strtoupper(bin2hex($raw)));
        }

        $trimmed = trim($raw, " \t\n\r\0\x0B\"");

        if (preg_match('/Hex-STRING:\s*([0-9A-Fa-f]{2}(?:\s+[0-9A-Fa-f]{2}){5})/i', $trimmed, $m)) {
            $hex = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? '');

            return self::normalizeColon($hex);
        }

        if (preg_match('/([0-9A-Fa-f]{2}[\s:-]){5}[0-9A-Fa-f]{2}/', $trimmed, $m)) {
            $hex = preg_replace('/[^0-9A-Fa-f]/', '', $m[0]) ?? '';

            return strlen($hex) === 12 ? self::normalizeColon($hex) : null;
        }

        return self::normalizeColon($trimmed);
    }
}
