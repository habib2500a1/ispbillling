<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Support\BillingMetricsCache;

/**
 * Syncs legacy portal current-month billing grid (AjaxCustomerBillList) into local invoices
 * so dashboard due/collection/monthly bill match pay.anetbd.com exactly.
 */
final class LegacyPortalCurrentBillingSyncService
{
    public function __construct(
        private readonly int $tenantId = 1,
        private readonly ?CustomerDueSnapshotApplicator $applicator = null,
        private readonly ?LegacyPortalBillingReconciler $reconciler = null,
    ) {}

    private function applicator(): CustomerDueSnapshotApplicator
    {
        return $this->applicator ?? new CustomerDueSnapshotApplicator($this->tenantId, $this->reconciler);
    }

    private function reconciler(): LegacyPortalBillingReconciler
    {
        return $this->reconciler ?? app(LegacyPortalBillingReconciler::class);
    }

    /**
     * @return array{customers: int, invoices: int, skipped: int, summary: array<string, float>}
     */
    public function syncAll(LegacyPortalSessionClient $client): array
    {
        $byHeader = $this->customersByLegacyHeaderId();
        $byCode = $this->customersByCustomerCode();
        /** @var array<int, true> $syncedCustomerIds */
        $syncedCustomerIds = [];
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
                $customer = $this->resolveCustomerForBillingRow($row, $byHeader, $byCode);
                if ($customer === null) {
                    $stats['skipped']++;

                    continue;
                }

                $this->syncCustomerRow($customer, $row, $periodKey);
                $syncedCustomerIds[(int) $customer->id] = true;
                $stats['customers']++;
                $stats['invoices']++;
            }

            $start += $batch;
        }

        $summary = $client->fetchBillingListOtherData();
        $this->cacheDashboardSummary($summary);
        BillingMetricsCache::flush($this->tenantId);
        $this->reconcileCustomersNotOnBillingGrid($syncedCustomerIds, $periodKey);

        return array_merge($stats, ['summary' => $summary]);
    }

    /**
     * @param  array<int, true>  $syncedCustomerIds
     */
    private function reconcileCustomersNotOnBillingGrid(array $syncedCustomerIds, string $periodKey): void
    {
        $reconciler = $this->reconciler();
        $aligner = app(LegacyPortalInvoiceAligner::class);

        Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->fromLegacyPortal()
            ->orderBy('id')
            ->each(function (Customer $customer) use ($syncedCustomerIds, $periodKey, $reconciler, $aligner): void {
                if (isset($syncedCustomerIds[(int) $customer->id])) {
                    return;
                }

                $aligner->voidStaleOpenInvoices($customer, $periodKey);
                $aligner->voidAllOpenInvoicesWhenIspDueZero($customer, 0);
                $reconciler->reconcile($customer, 'ISD-'.$customer->customer_code.'-'.$periodKey, 0, 0, 0);

                $meta = is_array($customer->meta) ? $customer->meta : [];
                $meta['legacy_portal_balance_due'] = 0;
                $meta['legacy_portal_payable'] = 0;
                $meta['legacy_portal_paid_mtd'] = 0;
                $meta['legacy_portal_billing_synced_at'] = now()->toIso8601String();
                $meta['balance_due'] = 0;
                $meta['billing_payment_state'] = 'paid';
                $customer->updateQuietly(['meta' => $meta]);
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
            'legacy portal current billing sync',
        );

        $customer = $customer->fresh() ?? $customer;
        app(LegacyPortalOverdueEvaluator::class)->syncLineGraceFromBillingRow($customer, $row);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $billingDay = (int) preg_replace('/\D+/', '', (string) ($row['BillingLastDate'] ?? ''));
        if ($billingDay >= 1 && $billingDay <= 31) {
            $meta['legacy_portal_billing_last_day'] = $billingDay;
        }
        $meta['legacy_portal_advance'] = $advance;
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
        app(LegacyPortalDashboardSummaryProvider::class)->storeSummary($this->tenantId, $summary);
    }

    /**
     * @return array<string, float>|null
     */
    public function cachedSummary(?int $tenantId = null): ?array
    {
        $tenantId = $tenantId ?? $this->tenantId;

        return app(LegacyPortalDashboardSummaryProvider::class)->summary($tenantId, false);
    }

    /**
     * @param  Collection<string, Customer>  $byHeader
     * @param  Collection<string, Customer>  $byCode
     */
    private function resolveCustomerForBillingRow(
        array $row,
        Collection $byHeader,
        Collection $byCode,
    ): ?Customer {
        $headerId = (string) ($row['CustomerHeaderId'] ?? '');
        $code = trim((string) ($row['CustomerId'] ?? ''));

        $customer = $headerId !== '' ? $byHeader->get($headerId) : null;
        if ($customer === null && $code !== '') {
            $customer = $byCode->get($code);
        }

        if ($customer === null) {
            return null;
        }

        if ($headerId !== '' && blank($customer->meta['legacy_id'] ?? null)) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            $meta['legacy_id'] = $headerId;
            $meta['legacy_client_id'] = $code !== '' ? $code : (string) ($meta['legacy_client_id'] ?? '');
            $customer->updateQuietly(['meta' => $meta]);
            $byHeader->put($headerId, $customer->fresh() ?? $customer);
        }

        return $customer->fresh() ?? $customer;
    }

    /**
     * @return Collection<string, Customer>
     */
    private function customersByLegacyHeaderId(): Collection
    {
        return Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->fromLegacyPortal()
            ->get()
            ->filter(fn (Customer $c): bool => filled($c->meta['legacy_id'] ?? null))
            ->keyBy(fn (Customer $c): string => (string) $c->meta['legacy_id']);
    }

    /**
     * @return Collection<string, Customer>
     */
    private function customersByCustomerCode(): Collection
    {
        return Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->fromLegacyPortal()
            ->get()
            ->keyBy(fn (Customer $c): string => (string) $c->customer_code);
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
