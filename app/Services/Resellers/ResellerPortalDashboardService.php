<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ResellerPortalDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Reseller $reseller): array
    {
        $customerIds = $reseller->customers()->pluck('id');
        $customerBase = $reseller->customers();
        $totalCustomers = (clone $customerBase)->count();
        $activeCustomers = (clone $customerBase)->where('status', 'active')->count();
        $onlineCustomers = (clone $customerBase)->where('is_ppp_online', true)->count();

        $todayCollection = 0.0;
        $monthCollection = 0.0;
        $todayCollectionCount = 0;
        $monthCollectionCount = 0;
        $dueAmount = 0.0;
        $recentPaymentAt = null;
        $collectionRate = 0.0;

        if ($customerIds->isNotEmpty()) {
            $paymentBase = Payment::query()
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed');

            $todayPayments = (clone $paymentBase)->whereDate('paid_at', today());
            $todayCollection = (float) (clone $todayPayments)->sum('amount');
            $todayCollectionCount = (int) (clone $todayPayments)->count();

            $monthPayments = (clone $paymentBase)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year);
            $monthCollection = (float) (clone $monthPayments)->sum('amount');
            $monthCollectionCount = (int) (clone $monthPayments)->count();
            $recentPaymentAt = (clone $paymentBase)->max('paid_at');

            $dueAmount = (float) Invoice::query()
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->sum(DB::raw('GREATEST(0, total - amount_paid)'));

            $monthInvoiced = (float) Invoice::query()
                ->whereIn('customer_id', $customerIds)
                ->whereNotIn('status', ['void', 'cancelled'])
                ->whereMonth('issue_date', now()->month)
                ->whereYear('issue_date', now()->year)
                ->sum('total');

            $collectionRate = $monthInvoiced > 0
                ? round(($monthCollection / $monthInvoiced) * 100, 1)
                : 0.0;
        }

        $dueCustomers = (clone $customerBase)
            ->whereHas('invoices', fn ($q) => $q->whereIn('status', ['open', 'partial']))
            ->count();

        $onuOnline = (clone $customerBase)
            ->whereNotNull('onu_device_id')
            ->where('is_ppp_online', true)
            ->count();

        $weakSignal = (clone $customerBase)
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
        $openTickets = $this->openTicketCount($reseller);

        $alerts = [];
        if ($dueCustomers > 0) {
            $alerts[] = [
                'tone' => 'rose',
                'title' => 'Due subscribers need collection',
                'value' => $dueCustomers.' accounts',
                'hint' => number_format($dueAmount, 0).' BDT outstanding',
            ];
        }
        if ($weakSignal > 0) {
            $alerts[] = [
                'tone' => 'amber',
                'title' => 'Weak ONU signals detected',
                'value' => $weakSignal.' ONU',
                'hint' => 'Check optical status and support queue',
            ];
        }
        if ($pendingSettlements > 0) {
            $alerts[] = [
                'tone' => 'sky',
                'title' => 'Settlement action pending',
                'value' => number_format($pendingSettlements, 0).' BDT',
                'hint' => 'Pending payout requests waiting review',
            ];
        }
        if ($openTickets > 0) {
            $alerts[] = [
                'tone' => 'violet',
                'title' => 'Open support tickets',
                'value' => $openTickets.' ticket(s)',
                'hint' => 'Follow up with subscribers before churn',
            ];
        }

        return [
            'customers_total' => $totalCustomers,
            'customers_active' => $activeCustomers,
            'customers_online' => $onlineCustomers,
            'customers_offline' => max(0, $activeCustomers - $onlineCustomers),
            'sub_resellers' => $reseller->children()->count(),
            'wallet' => (float) $reseller->wallet_balance,
            'pending_commission' => (float) $reseller->commissions()->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount'),
            'paid_commission_month' => $paidCommissionMonth,
            'today_collection' => $todayCollection,
            'today_collection_count' => $todayCollectionCount,
            'month_collection' => $monthCollection,
            'month_collection_count' => $monthCollectionCount,
            'collection_rate' => $collectionRate,
            'due_customers' => $dueCustomers,
            'due_amount' => round($dueAmount, 2),
            'pending_settlements' => $pendingSettlements,
            'onu_online' => $onuOnline,
            'weak_signal_onu' => $weakSignal,
            'open_tickets' => $openTickets,
            'outstanding_balance' => app(ResellerSettlementService::class)->outstandingBalance($reseller),
            'recent_payment_at' => $recentPaymentAt ? Carbon::parse($recentPaymentAt)->diffForHumans() : null,
            'alerts' => $alerts,
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
