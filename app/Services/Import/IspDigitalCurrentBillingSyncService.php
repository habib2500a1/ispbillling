<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Support\BillingMetricsCache;
use Illuminate\Support\Facades\Cache;

/**
 * Syncs ISP Digital current-month billing grid (AjaxCustomerBillList) into local invoices
 * so dashboard due/collection/monthly bill match pay.anetbd.com exactly.
 */
final class IspDigitalCurrentBillingSyncService
{
    public function __construct(
        private readonly int $tenantId = 1,
        private readonly ?CustomerDueSnapshotApplicator $applicator = null,
        private readonly ?IspDigitalBillingReconciler $reconciler = null,
    ) {}

    private function applicator(): CustomerDueSnapshotApplicator
    {
        return $this->applicator ?? new CustomerDueSnapshotApplicator($this->tenantId, $this->reconciler);
    }

    private function reconciler(): IspDigitalBillingReconciler
    {
        return $this->reconciler ?? app(IspDigitalBillingReconciler::class);
    }

    /**
     * @return array{customers: int, invoices: int, skipped: int, summary: array<string, float>}
     */
    public function syncAll(IspDigitalSessionClient $client): array
    {
        $customers = $this->customersByLegacyHeaderId();
        $stats = ['customers' => 0, 'invoices' => 0, 'skipped' => 0];
        $periodKey = now()->format('Y-m');
        $start = 0;
        $batch = 200;
        $total = PHP_INT_MAX;

        while ($start < $total) {
            $page = $client->fetchCustomerBillListPage($start, $batch);
            $total = $page['iTotalDisplayRecords'];
            $rows = $page['aaData'];

            foreach ($rows as $row) {
                $headerId = (string) ($row['CustomerHeaderId'] ?? '');
                $customer = $customers->get($headerId);
                if ($customer === null) {
                    $stats['skipped']++;

                    continue;
                }

                $this->syncCustomerRow($customer, $row, $periodKey);
                $stats['customers']++;
                $stats['invoices']++;
            }

            $start += $batch;
        }

        $summary = $client->fetchBillingListOtherData();
        $this->cacheDashboardSummary($summary);
        BillingMetricsCache::flush($this->tenantId);
        $this->reconcileCustomersNotOnBillingGrid($customers->keys()->all(), $periodKey);

        return array_merge($stats, ['summary' => $summary]);
    }

    /**
     * @param  list<string>  $syncedLegacyIds
     */
    private function reconcileCustomersNotOnBillingGrid(array $syncedLegacyIds, string $periodKey): void
    {
        $reconciler = $this->reconciler();

        Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->where('import_source', 'isp_digital')
            ->get()
            ->each(function (Customer $customer) use ($syncedLegacyIds, $periodKey, $reconciler): void {
                $legacyId = (string) ($customer->meta['legacy_id'] ?? '');
                if ($legacyId !== '' && in_array($legacyId, $syncedLegacyIds, true)) {
                    return;
                }

                $due = (float) ($customer->meta['isp_digital_balance_due'] ?? 0);
                if ($due <= 0.009) {
                    $reconciler->reconcile($customer, 'ISD-'.$customer->customer_code.'-'.$periodKey, 0, 0, 0);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncCustomerRow(Customer $customer, array $row, string $periodKey): void
    {
        $payable = $this->parseMoney($row['PayabaleBill'] ?? 0);
        $paid = $this->parseMoney($row['PaidAmount'] ?? 0);
        $balanceDue = $this->parseMoney($row['BalanceDue'] ?? 0);
        $advance = $this->parseMoney($row['AdvancePayemnt'] ?? $row['AdvancePayment'] ?? 0);

        $this->applicator()->apply(
            $customer,
            $payable,
            $paid,
            $balanceDue,
            $this->resolveDueDate($customer, $row),
            'ISP Digital current billing sync',
        );

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['isp_digital_advance'] = $advance;
        $customer->updateQuietly([
            'meta' => $meta,
            'billing_mode' => $this->reconciler()->resolveBillingMode($customer, $row, $balanceDue, $paid, $payable, $advance),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveDueDate(Customer $customer, array $row): Carbon
    {
        $day = (int) preg_replace('/\D+/', '', (string) ($row['BillingLastDate'] ?? $customer->billing_day ?? 15));

        if ($day >= 1 && $day <= 28) {
            return now()->day(min($day, (int) now()->daysInMonth));
        }

        return now()->endOfMonth();
    }

    /**
     * @param  array<string, float>  $summary
     */
    private function cacheDashboardSummary(array $summary): void
    {
        Cache::put(
            "isp_digital:billing_summary:{$this->tenantId}",
            array_merge($summary, ['synced_at' => now()->toIso8601String()]),
            now()->addMinutes(15),
        );
    }

    /**
     * @return array<string, float>|null
     */
    public function cachedSummary(?int $tenantId = null): ?array
    {
        $tenantId = $tenantId ?? $this->tenantId;
        /** @var array<string, float>|null $cached */
        $cached = Cache::get("isp_digital:billing_summary:{$tenantId}");

        return $cached;
    }

    /**
     * @return Collection<string, Customer>
     */
    private function customersByLegacyHeaderId(): Collection
    {
        return Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->where('import_source', 'isp_digital')
            ->get()
            ->filter(fn (Customer $c): bool => filled($c->meta['legacy_id'] ?? null))
            ->keyBy(fn (Customer $c): string => (string) $c->meta['legacy_id']);
    }

    private function parseMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value) ?? '';

        return round((float) ($clean !== '' ? $clean : 0), 2);
    }
}
