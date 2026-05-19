<?php

namespace App\Services\Accounting;

use App\Models\BankAccount;
use App\Models\VendorPayment;

class VendorPaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function recordPayment(VendorPayment $payment): VendorPayment
    {
        if ($payment->journal_entry_id) {
            return $payment;
        }

        $expenseCode = config('accounting.vendor_expense_code', '5200');
        $vatCode = config('accounting.vat_payable_code', '2100');
        $cashCode = config('accounting.cash_account_code', '1000');
        $bankCode = config('accounting.bank_account_code', '1100');

        $amount = (float) $payment->amount;
        $vat = (float) $payment->vat_amount;
        $netExpense = round($amount - $vat, 2);

        $creditAccountCode = $payment->payment_method === 'cash' ? $cashCode : $bankCode;
        $bankAccountId = $payment->bank_account_id;

        $lines = [
            ['account_code' => $expenseCode, 'debit' => $netExpense, 'description' => $payment->vendor?->name],
        ];
        if ($vat > 0) {
            $lines[] = ['account_code' => $vatCode, 'debit' => $vat, 'description' => 'Input VAT'];
        }
        $lines[] = [
            'account_code' => $creditAccountCode,
            'credit' => $amount,
            'bank_account_id' => $bankAccountId,
            'description' => $payment->vendor?->name,
        ];

        $journal = $this->ledger->post(
            'Vendor payment: '.($payment->vendor?->name ?? '#'.$payment->vendor_id),
            $lines,
            $payment->payment_date,
            'vendor_payment',
            $payment->id,
            (int) $payment->tenant_id,
        );

        $payment->update(['journal_entry_id' => $journal->id]);

        return $payment->fresh();
    }
}
