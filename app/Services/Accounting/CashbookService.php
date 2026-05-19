<?php

namespace App\Services\Accounting;

use App\Models\CashbookEntry;
use App\Support\TenantResolver;

class CashbookService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function record(
        string $direction,
        float $amount,
        string $partyName,
        ?string $accountCode,
        string $paymentMethod = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        ?\DateTimeInterface $date = null,
    ): CashbookEntry {
        $tenantId = TenantResolver::requiredTenantId();
        $amount = round($amount, 2);
        $date = $date ?? now();
        $isReceipt = $direction === 'in';

        $cashCode = config('accounting.cash_account_code', '1000');
        $categoryCode = $accountCode ?? ($isReceipt
            ? config('accounting.revenue_account_code', '4000')
            : config('accounting.vendor_expense_code', '5200'));

        $lines = $isReceipt
            ? [
                ['account_code' => $cashCode, 'debit' => $amount, 'description' => $partyName],
                ['account_code' => $categoryCode, 'credit' => $amount, 'description' => $partyName],
            ]
            : [
                ['account_code' => $categoryCode, 'debit' => $amount, 'description' => $partyName],
                ['account_code' => $cashCode, 'credit' => $amount, 'description' => $partyName],
            ];

        $journal = $this->ledger->post(
            ($isReceipt ? 'Cash receipt' : 'Cash payment').': '.$partyName,
            $lines,
            $date,
            'cashbook',
        );

        return CashbookEntry::create([
            'tenant_id' => $tenantId,
            'entry_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'party_name' => $partyName,
            'chart_account_id' => $this->ledger->accountByCode($categoryCode, $tenantId)->id,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'notes' => $notes,
            'journal_entry_id' => $journal->id,
            'created_by' => auth()->id(),
        ]);
    }

    public function runningBalance(?\DateTimeInterface $asOf = null): float
    {
        $cashAccount = $this->ledger->accountByCode(config('accounting.cash_account_code', '1000'));

        return $this->ledger->accountBalance($cashAccount->id, $asOf);
    }
}
