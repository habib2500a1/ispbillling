<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Support\IspDigitalPackageSpeed;
final class IspDigitalCustomerPackageSyncService
{
    public function __construct(
        private readonly IspDigitalCustomerImporter $importer = new IspDigitalCustomerImporter,
    ) {}

    /**
     * @return array{updated: int, skipped: int, missing: int, failed: int}
     */
    public function syncAll(IspDigitalSessionClient $client, string $query = 'alloverclients'): array
    {
        $client->login();

        $probe = $client->fetchCustomerPage(0, 1, $query);
        $total = (int) ($probe['iTotalDisplayRecords'] ?? 0);

        $stats = ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];
        $batch = 100;

        for ($offset = 0; $offset < $total; $offset += $batch) {
            $length = min($batch, $total - $offset);
            $page = $client->fetchCustomerPage($offset, $length, $query);

            foreach ($page['aaData'] as $row) {
                try {
                    $result = $this->syncRow($row);
                    $stats[$result]++;
                } catch (\Throwable) {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'updated'|'skipped'|'missing'
     */
    public function syncRow(array $row): string
    {
        $code = trim((string) ($row['CustomerId'] ?? ''));
        if ($code === '') {
            return 'skipped';
        }

        $customer = Customer::query()->where('customer_code', $code)->first();
        if ($customer === null) {
            return 'missing';
        }

        $packageId = $this->importer->resolvePackageIdForRow($row);
        $parsed = IspDigitalPackageSpeed::parse($row);
        $monthly = (float) ($row['MonthlyBill'] ?? 0);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['isp_digital_package_label'] = $parsed['display_name'] !== '' ? $parsed['display_name'] : null;
        $meta['package_speed'] = (string) ($row['PackageSpeed'] ?? '');
        $meta['mikrotik_profile'] = $parsed['mikrotik_profile'];
        $meta['monthly_bill_snapshot'] = $monthly > 0 ? $monthly : ($meta['monthly_bill_snapshot'] ?? null);

        $changes = false;

        if ($packageId !== null && (int) $customer->package_id !== (int) $packageId) {
            $customer->package_id = $packageId;
            $changes = true;
        }

        if ($meta !== $customer->meta) {
            $customer->meta = $meta;
            $changes = true;
        }

        if (! $changes) {
            return 'skipped';
        }

        $customer->save();

        return 'updated';
    }

    /**
     * @param  list<int>  $customerIds
     * @return array{updated: int, skipped: int, failed: int}
     */
    public function syncCustomersByIds(IspDigitalSessionClient $client, array $customerIds): array
    {
        $client->login();

        $codes = Customer::query()
            ->whereIn('id', $customerIds)
            ->pluck('customer_code', 'id');

        $stats = ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        $codeSet = array_flip($codes->values()->all());
        $batch = 100;
        $offset = 0;
        $total = PHP_INT_MAX;

        while ($offset < $total) {
            $page = $client->fetchCustomerPage($offset, $batch);
            $total = (int) ($page['iTotalDisplayRecords'] ?? 0);

            foreach ($page['aaData'] as $row) {
                $code = trim((string) ($row['CustomerId'] ?? ''));
                if ($code === '' || ! isset($codeSet[$code])) {
                    continue;
                }

                try {
                    $result = $this->syncRow($row);
                    if ($result === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable) {
                    $stats['failed']++;
                }
            }

            $offset += $batch;

            if ($stats['updated'] + $stats['skipped'] >= $codes->count()) {
                break;
            }
        }

        return $stats;
    }
}
