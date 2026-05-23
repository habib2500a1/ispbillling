<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerSettlement;
use Illuminate\Support\Facades\DB;

final class ResellerPortalDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Reseller $reseller): array
    {
        $customerIds = $reseller->customers()->pluck('id');

        $todayCollection = 0.0;
        $monthCollection = 0.0;

        if ($customerIds->isNotEmpty()) {
            $todayCollection = (float) DB::table('payments')
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed')
                ->whereDate('paid_at', today())
                ->sum('amount');

            $monthCollection = (float) DB::table('payments')
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount');
        }

        $dueCustomers = $reseller->customers()
            ->whereHas('invoices', fn ($q) => $q->whereIn('status', ['open', 'partial']))
            ->count();

        $onuOnline = $reseller->customers()
            ->whereNotNull('onu_device_id')
            ->where('is_ppp_online', true)
            ->count();

        $weakSignal = $reseller->customers()
            ->whereNotNull('onu_rx_dbm')
            ->where('onu_rx_dbm', '<', -27)
            ->count();

        $pendingSettlements = (float) $reseller->settlements()
            ->where('status', ResellerSettlement::STATUS_PENDING)
            ->sum('net_amount');

        $paidCommissionMonth = (float) $reseller->commissions()
            ->where('status', ResellerCommission::STATUS_PAID)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('commission_amount');

        return [
            'customers_total' => $reseller->customers()->count(),
            'customers_active' => $reseller->customers()->where('status', 'active')->count(),
            'customers_online' => $reseller->customers()->where('is_ppp_online', true)->count(),
            'customers_offline' => max(0, $reseller->customers()->where('status', 'active')->count() - $reseller->customers()->where('is_ppp_online', true)->count()),
            'sub_resellers' => $reseller->children()->count(),
            'wallet' => (float) $reseller->wallet_balance,
            'pending_commission' => (float) $reseller->commissions()->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount'),
            'paid_commission_month' => $paidCommissionMonth,
            'today_collection' => $todayCollection,
            'month_collection' => $monthCollection,
            'due_customers' => $dueCustomers,
            'pending_settlements' => $pendingSettlements,
            'onu_online' => $onuOnline,
            'weak_signal_onu' => $weakSignal,
            'open_tickets' => $this->openTicketCount($reseller),
            'outstanding_balance' => app(ResellerSettlementService::class)->outstandingBalance($reseller),
        ];
    }

    private function openTicketCount(Reseller $reseller): int
    {
        if (! class_exists(\App\Models\SupportTicket::class)) {
            return 0;
        }

        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return 0;
        }

        return (int) \App\Models\SupportTicket::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->count();
    }
}
