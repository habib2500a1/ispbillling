<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Import\LegacyPortalCurrentBillingSyncService;
use App\Services\Import\LegacyPortalDashboardSummaryProvider;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class StaffBillingKpiResolver
{
    /**
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}
     */
    public function resolve(int $tenantId): array
    {
        return Cache::remember(
            'staff_billing_kpi:'.$tenantId,
            120,
            fn (): array => $this->resolveUncached($tenantId),
        );
    }

    /**
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}
     */
    private function resolveUncached(int $tenantId): array
    {
        $provider = app(LegacyPortalDashboardSummaryProvider::class);

        if ($provider->tenantUsesLegacyPortal($tenantId)) {
            $summary = $provider->summary($tenantId);
            if ($summary !== null) {
                return $this->payloadFromIspSummary($summary);
            }

            $aggregated = $this->aggregatedLegacyPortalKpis($tenantId);
            if ($aggregated !== null) {
                return $aggregated;
            }
        }

        $cached = app(LegacyPortalCurrentBillingSyncService::class)->cachedSummary($tenantId);
        if ($cached !== null) {
            return $this->payloadFromIspSummary($cached);
        }

        return $this->localKpis($tenantId);
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}
     */
    private function payloadFromIspSummary(array $cached): array
    {
        return [
            'monthly_bill' => round((float) ($cached['monthly_bill'] ?? 0), 2),
            'collected_bill' => round((float) ($cached['collected_bill'] ?? 0), 2),
            'due' => round(max(0, (float) ($cached['due'] ?? 0)), 2),
            'discount' => round((float) ($cached['discount'] ?? 0), 2),
            'source' => 'legacy_portal',
        ];
    }

    /**
     * Local accounting only — not used for legacy portal tenants (avoids inflated open-invoice due).
     *
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}
     */
    private function localKpis(int $tenantId): array
    {
        $periodKey = now()->format('Y-m');
        $monthlyBill = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', 'ISD-%-'.$periodKey)
            ->sum('total');

        if ($monthlyBill <= 0) {
            $monthlyBill = (float) Customer::withoutGlobalScopes()
                ->where('customers.tenant_id', $tenantId)
                ->where('customers.status', CustomerStatus::ACTIVE)
                ->whereNotNull('customers.package_id')
                ->join('packages', 'packages.id', '=', 'customers.package_id')
                ->sum('packages.price_monthly');
        }

        $collected = (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY])
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        if ($collected <= 0) {
            $collected = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('invoice_number', 'like', 'ISD-%-'.$periodKey)
                ->sum('amount_paid');
        }

        $due = CustomerBalanceDue::tenantOpenInvoiceDueSum($tenantId);

        $discount = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum(DB::raw('COALESCE(discount_amount, 0) + COALESCE(coupon_discount_amount, 0)'));

        return [
            'monthly_bill' => round($monthlyBill, 2),
            'collected_bill' => round($collected, 2),
            'due' => round(max(0, $due), 2),
            'discount' => round(abs($discount), 2),
            'source' => 'local',
        ];
    }

    /**
     * Fallback when legacy portal summary API is unavailable (uses synced per-customer meta, not open invoices).
     *
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}|null
     */
    private function aggregatedLegacyPortalKpis(int $tenantId): ?array
    {
        $syncedCount = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->whereNotNull('meta->legacy_portal_billing_synced_at')
            ->count();

        if ($syncedCount < 1) {
            return null;
        }

        $due = 0.0;
        $payable = 0.0;
        $paidMtd = 0.0;

        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->whereNotNull('meta->legacy_portal_billing_synced_at')
            ->select(['id', 'meta', 'import_source'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$due, &$payable, &$paidMtd): void {
                foreach ($chunk as $customer) {
                    $meta = is_array($customer->meta) ? $customer->meta : [];
                    $due += (float) ($meta['legacy_portal_balance_due'] ?? 0);
                    $payable += (float) ($meta['legacy_portal_payable'] ?? 0);
                    $paidMtd += (float) ($meta['legacy_portal_paid_mtd'] ?? 0);
                }
            });

        $periodKey = now()->format('Y-m');
        $monthlyBill = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', 'ISD-%-'.$periodKey)
            ->whereNotIn('status', ['void', 'cancelled'])
            ->sum('total');

        if ($monthlyBill <= 0.009) {
            $monthlyBill = round($payable, 2);
        }

        $staleSummary = app(LegacyPortalDashboardSummaryProvider::class)->summary($tenantId, false);
        $collected = $staleSummary !== null
            ? (float) ($staleSummary['collected_bill'] ?? 0)
            : round(max(0, $paidMtd), 2);

        return [
            'monthly_bill' => round($monthlyBill, 2),
            'collected_bill' => round($collected, 2),
            'due' => round(max(0, $due), 2),
            'discount' => 0,
            'source' => 'legacy_portal',
        ];
    }

    public function dueClientsCount(int $tenantId): int
    {
        return (int) Cache::remember(
            'staff_billing_due_clients:'.$tenantId,
            120,
            fn (): int => $this->dueClientsCountUncached($tenantId),
        );
    }

    private function dueClientsCountUncached(int $tenantId): int
    {
        if (app(LegacyPortalDashboardSummaryProvider::class)->tenantUsesLegacyPortal($tenantId)) {
            $summary = app(LegacyPortalDashboardSummaryProvider::class)->summary($tenantId, false);
            if ($summary !== null && isset($summary['total_unpaid_clients'])) {
                return (int) $summary['total_unpaid_clients'];
            }
        }

        $fromInvoices = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereHas('invoices', fn ($q) => $q->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                ->whereRaw('(total - amount_paid) > 0.009'))
            ->count();

        if ($fromInvoices > 0) {
            return $fromInvoices;
        }

        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->get()
            ->filter(fn (Customer $c): bool => CustomerBalanceDue::amount($c) > 0.009)
            ->count();
    }
}
