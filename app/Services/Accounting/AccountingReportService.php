<?php

namespace App\Services\Accounting;

use App\Models\CashbookEntry;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\VendorPayment;
use App\Support\AccountType;
use App\Support\TenantResolver;
use Carbon\Carbon;

class AccountingReportService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @return array{income: float, expenses: float, net_profit: float, lines: list<array{code: string, name: string, type: string, amount: float}>}
     */
    public function profitAndLoss(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $lines = [];
        $income = 0.0;
        $expenses = 0.0;

        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [AccountType::INCOME, AccountType::EXPENSE])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        foreach ($accounts as $account) {
            $amount = $this->periodMovement($account->id, $from, $to, $account->type);
            if (abs($amount) < 0.01) {
                continue;
            }
            $lines[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'amount' => $amount,
            ];
            if ($account->type === AccountType::INCOME) {
                $income += $amount;
            } else {
                $expenses += $amount;
            }
        }

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net_profit' => round($income - $expenses, 2),
            'lines' => $lines,
        ];
    }

    /**
     * @return array{output_vat: float, input_vat: float, net_vat_payable: float, invoice_tax: float, vendor_vat: float}
     */
    public function vatReport(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $invoiceTax = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum('tax_amount');

        $vendorVat = (float) VendorPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('vat_amount');

        $vatAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', config('accounting.vat_payable_code', '2100'))
            ->first();

        $ledgerVat = $vatAccount
            ? $this->periodMovement($vatAccount->id, $from, $to, AccountType::LIABILITY)
            : 0.0;

        return [
            'output_vat' => round($invoiceTax, 2),
            'input_vat' => round($vendorVat, 2),
            'net_vat_payable' => round($invoiceTax - $vendorVat, 2),
            'invoice_tax' => round($invoiceTax, 2),
            'vendor_vat' => round($vendorVat, 2),
            'ledger_vat_balance' => round($ledgerVat, 2),
        ];
    }

    /**
     * @return array{opening: float, receipts: float, payments: float, closing: float, entries: \Illuminate\Support\Collection<int, CashbookEntry>}
     */
    public function cashbookSummary(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $opening = app(CashbookService::class)->runningBalance($from->copy()->subDay());

        $entries = CashbookEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $receipts = (float) $entries->where('direction', 'in')->sum('amount');
        $payments = (float) $entries->where('direction', 'out')->sum('amount');

        return [
            'opening' => round($opening, 2),
            'receipts' => round($receipts, 2),
            'payments' => round($payments, 2),
            'closing' => round($opening + $receipts - $payments, 2),
            'entries' => $entries,
        ];
    }

    /**
     * @return array{collections: float, cashbook_in: float, cashbook_out: float}
     */
    public function incomeExpenseSnapshot(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $collections = (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $cashIn = (float) CashbookEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('direction', 'in')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $cashOut = (float) CashbookEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('direction', 'out')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        return [
            'collections' => round($collections, 2),
            'cashbook_in' => round($cashIn, 2),
            'cashbook_out' => round($cashOut, 2),
        ];
    }

    private function periodMovement(int $accountId, Carbon $from, Carbon $to, string $type): float
    {
        $account = ChartOfAccount::find($accountId);
        if (! $account) {
            return 0.0;
        }

        $debit = (float) JournalEntryLine::query()
            ->where('chart_account_id', $accountId)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()]))
            ->sum('debit');

        $credit = (float) JournalEntryLine::query()
            ->where('chart_account_id', $accountId)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()]))
            ->sum('credit');

        if ($type === AccountType::INCOME) {
            return round($credit - $debit, 2);
        }

        if ($type === AccountType::EXPENSE) {
            return round($debit - $credit, 2);
        }

        return round($credit - $debit, 2);
    }
}
