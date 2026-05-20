<?php

namespace App\Support;

/**
 * ISP Digital list rows expose package label in {@see Package} and MikroTik profile in {@see PackageSpeed}
 * as "{display}/{profile}" (e.g. "25Mbps/Packages>>1").
 */
final class IspDigitalPackageSpeed
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{display_name: string, mikrotik_profile: ?string}
     */
    public static function parse(array $row): array
    {
        $packageSpeed = trim((string) ($row['PackageSpeed'] ?? ''));
        $package = trim((string) ($row['Package'] ?? ''));

        if ($packageSpeed !== '' && str_contains($packageSpeed, '/')) {
            [$speedLabel, $profile] = explode('/', $packageSpeed, 2);
            $display = $package !== '' ? $package : trim($speedLabel);

            return [
                'display_name' => $display,
                'mikrotik_profile' => self::normalizeProfile($profile),
            ];
        }

        $display = $package !== '' ? $package : $packageSpeed;

        return [
            'display_name' => $display,
            'mikrotik_profile' => null,
        ];
    }

    public static function normalizeProfile(?string $profile): ?string
    {
        $profile = trim((string) $profile);

        return $profile !== '' ? $profile : null;
    }
}
