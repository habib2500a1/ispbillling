<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

/**
 * Import from a manual JSON export (Sheba-Fi or similar). Does not scrape demo.shebafi.com.
 *
 * Expected shape: { "customers": [ { "customer_code", "name", "phone", "package_name?", "status?" } ] }
 */
final class ShebaFiJsonImporter
{
    /**
     * @return array<string, int>
     */
    public function import(string $path, int $tenantId, bool $dryRun = false): array
    {
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            throw new \InvalidArgumentException('Invalid JSON file.');
        }

        $rows = $json['customers'] ?? $json['subscribers'] ?? $json;
        if (! is_array($rows)) {
            throw new \InvalidArgumentException('JSON must contain a customers array.');
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $packagesByName = Package::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'name');

        DB::transaction(function () use ($rows, $tenantId, $dryRun, $packagesByName, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''));
                $code = trim((string) ($row['customer_code'] ?? $row['id'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '' || ($phone === '' && $code === '')) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['updated']++;

                    continue;
                }

                try {
                    $customer = null;
                    if ($code !== '') {
                        $customer = Customer::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('customer_code', $code)
                            ->first();
                    }
                    if ($customer === null && $phone !== '') {
                        $customer = Customer::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('phone', 'like', '%'.substr($phone, -10))
                            ->first();
                    }

                    $packageName = trim((string) ($row['package_name'] ?? $row['package'] ?? ''));
                    $packageId = $packageName !== '' ? $packagesByName->get($packageName) : null;

                    $attrs = [
                        'tenant_id' => $tenantId,
                        'customer_code' => $code !== '' ? $code : null,
                        'name' => $name,
                        'phone' => $phone !== '' ? $phone : ($row['phone'] ?? null),
                        'status' => $this->mapStatus((string) ($row['status'] ?? 'active')),
                        'package_id' => $packageId,
                        'import_source' => 'sheba_fi_json',
                        'meta' => ['sheba_fi_import' => $row],
                    ];

                    if ($customer === null) {
                        if (blank($attrs['customer_code'])) {
                            $attrs['customer_code'] = 'SF-'.strtoupper(substr(md5($phone.$name), 0, 8));
                        }
                        Customer::createTrusted($attrs);
                        $stats['created']++;
                    } else {
                        $customer->updateTrusted(array_filter([
                            'name' => $name,
                            'phone' => $attrs['phone'],
                            'package_id' => $packageId,
                            'status' => $attrs['status'],
                            'meta' => array_merge($customer->meta ?? [], ['sheba_fi_import' => $row]),
                        ]));
                        $stats['updated']++;
                    }
                } catch (\Throwable) {
                    $stats['errors']++;
                }
            }
        });

        return $stats;
    }

    private function mapStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match (true) {
            str_contains($status, 'suspend') => 'suspended',
            str_contains($status, 'expire') => 'expired',
            str_contains($status, 'left'), str_contains($status, 'termin') => 'terminated',
            str_contains($status, 'pending') => 'pending',
            default => 'active',
        };
    }
}
