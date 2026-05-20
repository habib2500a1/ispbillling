<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Package;
use App\Support\IspDigitalPackageSpeed;

/**
 * Sync ISP Digital monthly bill (per user) and package price_monthly from live customer list + optional details HTML (ONU rent).
 */
final class IspDigitalPriceSyncService
{
    public function __construct(
        private readonly IspDigitalCustomerImporter $importer = new IspDigitalCustomerImporter,
        private readonly IspDigitalCustomerPackageSyncService $packages = new IspDigitalCustomerPackageSyncService,
        private readonly ?IspDigitalCustomerDetailsSyncService $details = null,
    ) {}

    /**
     * @return array{
     *     customers_updated: int,
     *     customers_skipped: int,
     *     packages_updated: int,
     *     onu_updated: int,
     *     onu_skipped: int,
     *     missing: int,
     *     failed: int
     * }
     */
    public function syncAll(IspDigitalSessionClient $client, string $query = 'alloverclients', bool $withOnuDetails = false): array
    {
        $client->login();

        $probe = $client->fetchCustomerPage(0, 1, $query);
        $total = (int) ($probe['iTotalDisplayRecords'] ?? 0);

        $stats = [
            'customers_updated' => 0,
            'customers_skipped' => 0,
            'packages_updated' => 0,
            'onu_updated' => 0,
            'onu_skipped' => 0,
            'missing' => 0,
            'failed' => 0,
        ];

        /** @var array<int, list<float>> $billsByPackage */
        $billsByPackage = [];
        $batch = 100;

        for ($offset = 0; $offset < $total; $offset += $batch) {
            $length = min($batch, $total - $offset);
            $page = $client->fetchCustomerPage($offset, $length, $query);

            foreach ($page['aaData'] as $row) {
                try {
                    $result = $this->syncCustomerRow($row, $billsByPackage);
                    $stats[$result]++;
                } catch (\Throwable) {
                    $stats['failed']++;
                }
            }
        }

        $stats['packages_updated'] = $this->applyPackagePrices($billsByPackage);

        if ($withOnuDetails) {
            $onu = $this->syncOnuPricesFromDetails();
            $stats['onu_updated'] = $onu['updated'];
            $stats['onu_skipped'] = $onu['skipped'];
        }

        return $stats;
    }

    /**
     * @param  array<int, list<float>>  $billsByPackage
     * @return 'customers_updated'|'customers_skipped'|'missing'
     */
    private function syncCustomerRow(array $row, array &$billsByPackage): string
    {
        $code = trim((string) ($row['CustomerId'] ?? ''));
        if ($code === '') {
            return 'customers_skipped';
        }

        $customer = Customer::query()->where('customer_code', $code)->first();
        if ($customer === null) {
            return 'missing';
        }

        $this->packages->syncRow($row);

        $customer->refresh();

        $monthly = $this->resolveMonthlyBill($row);
        $packageId = (int) ($customer->package_id ?? 0);

        if ($packageId > 0 && $monthly > 0) {
            $billsByPackage[$packageId] ??= [];
            $billsByPackage[$packageId][] = $monthly;
        }

        $parsed = IspDigitalPackageSpeed::parse($row);
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $nextMeta = array_merge($meta, [
            'isp_digital_package_label' => $parsed['display_name'] !== '' ? $parsed['display_name'] : ($meta['isp_digital_package_label'] ?? null),
            'package_speed' => (string) ($row['PackageSpeed'] ?? ''),
            'mikrotik_profile' => $parsed['mikrotik_profile'] ?? ($meta['mikrotik_profile'] ?? null),
            'monthly_bill_snapshot' => $monthly > 0 ? $monthly : ($meta['monthly_bill_snapshot'] ?? null),
            'isp_digital_monthly_bill' => $monthly > 0 ? $monthly : ($meta['isp_digital_monthly_bill'] ?? null),
        ]);

        if ($nextMeta === $meta && $monthly <= 0) {
            return 'customers_skipped';
        }

        $customer->forceFill(['meta' => $nextMeta])->saveQuietly();

        return 'customers_updated';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveMonthlyBill(array $row): float
    {
        $monthly = (float) ($row['MonthlyBill'] ?? 0);
        if ($monthly > 0) {
            return round($monthly, 2);
        }

        $raw = is_array($row['isp_digital_raw'] ?? null) ? $row['isp_digital_raw'] : [];

        return round((float) ($raw['MonthlyBill'] ?? 0), 2);
    }

    /**
     * @param  array<int, list<float>>  $billsByPackage
     */
    private function applyPackagePrices(array $billsByPackage): int
    {
        $updated = 0;

        foreach ($billsByPackage as $packageId => $amounts) {
            $amounts = array_values(array_filter($amounts, fn (float $v): bool => $v > 0));
            if ($amounts === []) {
                continue;
            }

            sort($amounts);
            $median = $amounts[(int) floor((count($amounts) - 1) / 2)];

            $package = Package::query()->find($packageId);
            if ($package === null) {
                continue;
            }

            if ((float) $package->price_monthly === $median) {
                continue;
            }

            $package->update(['price_monthly' => $median]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    public function syncOnuPricesFromDetails(): array
    {
        $details = $this->details ?? new IspDigitalCustomerDetailsSyncService;
        $stats = ['updated' => 0, 'skipped' => 0];

        Customer::query()
            ->where('import_source', 'isp_digital')
            ->orderBy('id')
            ->chunkById(40, function ($customers) use ($details, &$stats): void {
                foreach ($customers as $customer) {
                    $result = $details->syncCustomer($customer);
                    if ($result['updated']) {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                    usleep(80_000);
                }
            });

        return $stats;
    }
}
