<?php

namespace App\Services\Import\ISPTrack;

final class ISPTrackJsonLoader
{
    /**
     * @return array{
     *     packages: list<array<string, mixed>>,
     *     zones: list<array<string, mixed>>,
     *     sub_zones: list<array<string, mixed>>,
     *     boxes: list<array<string, mixed>>,
     *     mikrotik_servers: list<array<string, mixed>>,
     *     clients: list<array<string, mixed>>,
     *     billings: list<array<string, mixed>>,
     *     invoices: list<array<string, mixed>>,
     *     payments: list<array<string, mixed>>
     * }
     */
    public function load(string $path): array
    {
        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            throw new \InvalidArgumentException('ISPTrack export must be valid JSON.');
        }

        return [
            'packages' => $this->rows($raw, ['packages', 'package']),
            'zones' => $this->rows($raw, ['zones', 'zone']),
            'sub_zones' => $this->rows($raw, ['sub_zones', 'subzones', 'sub_zone']),
            'boxes' => $this->rows($raw, ['boxes', 'box']),
            'mikrotik_servers' => $this->rows($raw, ['mikrotik_servers', 'mikrotik']),
            'clients' => $this->rows($raw, ['clients', 'customers', 'subscribers']),
            'billings' => $this->rows($raw, ['billings', 'billing']),
            'invoices' => $this->rows($raw, ['invoices', 'invoice']),
            'payments' => $this->rows($raw, ['payments', 'payment']),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function rows(array $raw, array $keys): array
    {
        foreach ($keys as $key) {
            if (! isset($raw[$key])) {
                continue;
            }

            $value = $raw[$key];
            if (! is_array($value)) {
                continue;
            }

            return array_values(array_filter($value, fn ($row): bool => is_array($row)));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function str(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (filled($row[$key] ?? null)) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function num(array $row, string ...$keys): float
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                return (float) $row[$key];
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function int(array $row, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                return (int) $row[$key];
            }
        }

        return null;
    }
}
