<?php

namespace App\Support;

/**
 * Extract VLAN id from MikroTik PPP secret fields (comment, profile, raw API row).
 */
final class MikrotikVlanParser
{
    /**
     * @param  array<string, mixed>  $secretRow  fetchPppSecrets row (name, profile, comment, raw)
     */
    public static function fromPppSecret(array $secretRow, ?string $profileVlan = null): ?string
    {
        $raw = is_array($secretRow['raw'] ?? null) ? $secretRow['raw'] : [];

        foreach (['vlan-id', 'vlan_id', 'vlan'] as $key) {
            $vlan = self::normalizeVlan($raw[$key] ?? null);
            if ($vlan !== null) {
                return $vlan;
            }
        }

        $comment = $secretRow['comment'] ?? $raw['comment'] ?? null;
        $vlan = self::fromText(is_string($comment) ? $comment : null);
        if ($vlan !== null) {
            return $vlan;
        }

        $profile = $secretRow['profile'] ?? $raw['profile'] ?? null;
        $vlan = self::fromText(is_string($profile) ? $profile : null);
        if ($vlan !== null) {
            return $vlan;
        }

        if ($profileVlan !== null) {
            return self::normalizeVlan($profileVlan);
        }

        foreach (['routes', 'interface'] as $key) {
            $vlan = self::fromText(isset($raw[$key]) ? (string) $raw[$key] : null);
            if ($vlan !== null) {
                return $vlan;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profileRow  /ppp/profile print row
     */
    public static function fromPppProfile(array $profileRow): ?string
    {
        $name = isset($profileRow['name']) ? (string) $profileRow['name'] : null;

        return self::fromText($name)
            ?? self::fromText(isset($profileRow['comment']) ? (string) $profileRow['comment'] : null);
    }

    public static function fromInterfaceName(?string $interface): ?string
    {
        if ($interface === null || trim($interface) === '') {
            return null;
        }

        $interface = trim($interface);

        if (preg_match('/\bvlan[-_]?(\d{1,4})\b/i', $interface, $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        if (preg_match('/^vlan(\d{1,4})$/i', $interface, $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        return self::fromOltInterfaceLabel($interface);
    }

    /**
     * e.g. AVIES-OLT-507-KP OFFICE → 507 (when no /interface vlan row exists).
     */
    public static function fromOltInterfaceLabel(?string $interface): ?string
    {
        if ($interface === null || trim($interface) === '') {
            return null;
        }

        if (preg_match('/OLT[-_](\d{1,4})(?:\D|$)/i', trim($interface), $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        return null;
    }

    /**
     * PON index from RouterOS uplink name (e.g. KAJLA-BDCOM-OLT-P-6-MOSTOFA LANE → 6).
     */
    public static function ponIndexFromOltInterfaceLabel(?string $interface): ?int
    {
        if ($interface === null || trim($interface) === '') {
            return null;
        }

        if (preg_match('/-P-(\d+)(?:-|\s|$)/i', trim($interface), $m) !== 1) {
            return null;
        }

        $pon = (int) $m[1];

        return $pon >= 0 && $pon <= 128 ? $pon : null;
    }

    public static function fromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/\bvlan\s*[:=#]?\s*(\d{1,4})\b/i', $text, $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        if (preg_match('/\bvlan[-_]?(\d{1,4})(?:\D|$)/i', $text, $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        if (preg_match('/\bv(\d{1,4})\b/i', $text, $m) === 1) {
            return self::normalizeVlan($m[1]);
        }

        return null;
    }

    public static function normalizeVlan(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '' || ! ctype_digit($s)) {
            return null;
        }

        $n = (int) $s;
        if ($n < 1 || $n > 4094) {
            return null;
        }

        return (string) $n;
    }
}
