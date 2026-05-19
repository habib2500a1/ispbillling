<?php

namespace App\Services\Accounts;

use App\Models\BankAccount;
use App\Models\CollectorExpense;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\VendorPayment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\CashbookService;
use App\Services\Collector\CollectorWalletService;
use App\Support\TenantResolver;
use Carbon\Carbon;

class AccountsDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();
        $tenantId = TenantResolver::requiredTenantId();

        $reports = app(AccountingReportService::class);
        $pl = $reports->profitAndLoss($from, $to, $tenantId);
        $snap = $reports->incomeExpenseSnapshot($from, $to, $tenantId);
        $cash = app(CashbookService::class)->runningBalance();

        $collectorCash = 0.0;
        foreach (\App\Models\User::query()->where('tenant_id', $tenantId)->pluck('id') as $userId) {
            $w = app(CollectorWalletService::class)->wallet((int) $userId);
            $collectorCash += (float) ($w['cash_in_hand'] ?? 0);
        }

        return [
            'period_label' => $from->format('d/m/y').' → '.$to->format('d/m/y'),
            'income' => $pl['income'],
            'expenses' => $pl['expenses'],
            'net_profit' => $pl['net_profit'],
            'collections' => $snap['collections'],
            'cashbook_in' => $snap['cashbook_in'],
            'cashbook_out' => $snap['cashbook_out'],
            'cash_balance' => $cash,
            'bank_balance' => (float) BankAccount::query()->where('is_active', true)->sum('current_balance'),
            'collector_cash' => round($collectorCash, 2),
            'reseller_wallets' => (float) Reseller::query()->sum('wallet_balance'),
            'pending_commission' => (float) ResellerCommission::query()
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'expense_count' => (int) VendorPayment::query()
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'income_count' => (int) Payment::query()
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$from, $to])
                ->count(),
            'collector_expenses_pending' => (int) CollectorExpense::query()->where('status', 'pending')->count(),
        ];
    }
}
