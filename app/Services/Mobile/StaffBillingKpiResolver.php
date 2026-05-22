<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;

final class StaffBillingKpiResolver
{
    /**
     * @return array{monthly_bill: float, collected_bill: float, due: float, discount: float, source: string}
     */
    public function resolve(int $tenantId): array
    {
        $cached = app(IspDigitalCurrentBillingSyncService::class)->cachedSummary($tenantId);
        if ($cached !== null && ($cached['monthly_bill'] ?? 0) > 0) {
            return [
                'monthly_bill' => round((float) $cached['monthly_bill'], 2),
                'collected_bill' => round((float) $cached['collected_bill'], 2),
                'due' => round(max(0, CustomerBalanceDue::tenantOpenInvoiceDueSum($tenantId)), 2),
                'discount' => round((float) ($cached['discount'] ?? 0), 2),
                'source' => 'isp_digital',
            ];
        }

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

    public function dueClientsCount(int $tenantId): int
    {
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
            ->where('import_source', 'isp_digital')
            ->get()
            ->filter(fn (Customer $c): bool => CustomerBalanceDue::amount($c) > 0.009)
            ->count();
    }
}
