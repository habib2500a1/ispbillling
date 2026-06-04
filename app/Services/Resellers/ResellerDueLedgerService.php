<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use Carbon\Carbon;
use App\Models\ResellerLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerDueLedgerService
{
    public function isEnabled(): bool
    {
        return (bool) config('reseller_billing.hierarchical_enabled', true);
    }

    public function usesPostpaidDue(Reseller $reseller): bool
    {
        $mode = $reseller->billing_settlement_mode
            ?? config('reseller_billing.default_settlement_mode', 'postpaid_due');

        return in_array($mode, ['postpaid_due', 'hybrid'], true);
    }

    public function accrueFromInvoice(Invoice $invoice, ?float $amountOverride = null): ?ResellerLedgerEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $invoice->loadMissing('customer.reseller');
        $customer = $invoice->customer;
        $reseller = $customer?->reseller;
        if ($reseller === null || ! $this->usesPostpaidDue($reseller)) {
            return null;
        }

        $split = app(ResellerInvoiceSplitCalculator::class)->splitForInvoice($invoice);
        $wholesale = $amountOverride !== null
            ? round(min($amountOverride, $split['wholesale']), 2)
            : $split['wholesale'];
        if ($wholesale <= 0) {
            return null;
        }

        $reference = $amountOverride !== null
            ? 'INV-ACCR-PART-'.$invoice->id.'-'.(int) round($wholesale * 100)
            : 'INV-ACCR-'.$invoice->id;

        if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $reference)->exists()) {
            return ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $reference)->first();
        }

        $margin = $split['wholesale'] > 0
            ? round($split['margin'] * ($wholesale / $split['wholesale']), 2)
            : $split['margin'];

        return DB::transaction(function () use ($reseller, $invoice, $customer, $split, $wholesale, $margin, $reference): ResellerLedgerEntry {
            $locked = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $newDue = round((float) $locked->admin_receivable_due + $wholesale, 2);

            $entry = ResellerLedgerEntry::query()->create([
                'tenant_id' => $locked->tenant_id,
                'reseller_id' => $locked->id,
                'customer_id' => $customer?->id,
                'invoice_id' => $invoice->id,
                'entry_type' => ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL,
                'direction' => ResellerLedgerEntry::DIRECTION_DEBIT,
                'amount' => $wholesale,
                'admin_receivable_after' => $newDue,
                'retail_amount' => $split['retail'],
                'wholesale_amount' => $wholesale,
                'margin_amount' => $margin,
                'reference' => $reference,
                'notes' => sprintf(
                    'Admin receivable for bill %s · customer %s (retail %s, margin %s)',
                    $invoice->invoice_number,
                    $customer?->customer_code,
                    number_format($split['retail'], 2),
                    number_format($margin, 2),
                ),
            ]);

            $locked->forceFill(['admin_receivable_due' => $newDue])->saveQuietly();

            return $entry;
        });
    }

    public function applyCustomerPayment(Payment $payment): ?ResellerLedgerEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $payment->loadMissing(['customer.reseller', 'invoice']);
        $customer = $payment->customer;
        $reseller = $customer?->reseller;
        if ($reseller === null || ! $this->usesPostpaidDue($reseller)) {
            return null;
        }

        $invoice = $payment->invoice;
        if ($invoice === null) {
            return null;
        }

        $split = app(ResellerInvoiceSplitCalculator::class)->splitForInvoice($invoice);
        if ($split['retail'] <= 0) {
            return null;
        }

        $paid = (float) $payment->amount;
        $ratio = min(1.0, $paid / $split['retail']);
        $adminPortion = round($split['wholesale'] * $ratio, 2);
        $marginPortion = round($split['margin'] * $ratio, 2);

        if ($adminPortion <= 0 && $marginPortion <= 0) {
            return null;
        }

        $reference = 'PAY-APPLY-'.$payment->id;

        if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $reference)->exists()) {
            return ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $reference)->first();
        }

        return DB::transaction(function () use ($reseller, $payment, $invoice, $customer, $split, $adminPortion, $marginPortion, $reference): ResellerLedgerEntry {
            $locked = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $newDue = max(0, round((float) $locked->admin_receivable_due - $adminPortion, 2));
            $newMargin = round((float) $locked->margin_accrued_total + $marginPortion, 2);

            $entry = ResellerLedgerEntry::query()->create([
                'tenant_id' => $locked->tenant_id,
                'reseller_id' => $locked->id,
                'customer_id' => $customer?->id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'entry_type' => ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_COLLECTION,
                'direction' => ResellerLedgerEntry::DIRECTION_CREDIT,
                'amount' => $adminPortion,
                'admin_receivable_after' => $newDue,
                'retail_amount' => $split['retail'],
                'wholesale_amount' => $adminPortion,
                'margin_amount' => $marginPortion,
                'reference' => $reference,
                'notes' => 'Customer payment applied — admin portion reduced, margin recognized',
            ]);

            $locked->forceFill([
                'admin_receivable_due' => $newDue,
                'margin_accrued_total' => $newMargin,
            ])->saveQuietly();

            return $entry;
        });
    }

    public function recordSettlement(Reseller $reseller, float $amount, ?User $by = null, ?string $notes = null): ResellerLedgerEntry
    {
        return DB::transaction(function () use ($reseller, $amount, $by, $notes): ResellerLedgerEntry {
            $locked = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $newDue = max(0, round((float) $locked->admin_receivable_due - $amount, 2));

            $entry = ResellerLedgerEntry::query()->create([
                'tenant_id' => $locked->tenant_id,
                'reseller_id' => $locked->id,
                'entry_type' => ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_SETTLEMENT,
                'direction' => ResellerLedgerEntry::DIRECTION_CREDIT,
                'amount' => $amount,
                'admin_receivable_after' => $newDue,
                'reference' => 'SETTLE-'.now()->format('YmdHis'),
                'notes' => $notes,
                'created_by' => $by?->id,
            ]);

            $locked->forceFill(['admin_receivable_due' => $newDue])->saveQuietly();

            return $entry;
        });
    }

    public function recordCreditNote(Reseller $reseller, float $amount, ?User $by = null, ?string $notes = null): ResellerLedgerEntry
    {
        return $this->recordManualAdjustment(
            $reseller,
            $amount,
            ResellerLedgerEntry::TYPE_CREDIT_NOTE,
            ResellerLedgerEntry::DIRECTION_CREDIT,
            'CN',
            $by,
            $notes,
        );
    }

    public function recordDebitNote(Reseller $reseller, float $amount, ?User $by = null, ?string $notes = null): ResellerLedgerEntry
    {
        return $this->recordManualAdjustment(
            $reseller,
            $amount,
            ResellerLedgerEntry::TYPE_DEBIT_NOTE,
            ResellerLedgerEntry::DIRECTION_DEBIT,
            'DN',
            $by,
            $notes,
        );
    }

    private function recordManualAdjustment(
        Reseller $reseller,
        float $amount,
        string $entryType,
        string $direction,
        string $refPrefix,
        ?User $by = null,
        ?string $notes = null,
    ): ResellerLedgerEntry {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }

        return DB::transaction(function () use ($reseller, $amount, $entryType, $direction, $refPrefix, $by, $notes): ResellerLedgerEntry {
            $locked = Reseller::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($reseller->id);
            $delta = $direction === ResellerLedgerEntry::DIRECTION_DEBIT ? $amount : -$amount;
            $newDue = max(0, round((float) $locked->admin_receivable_due + $delta, 2));

            $entry = ResellerLedgerEntry::query()->create([
                'tenant_id' => $locked->tenant_id,
                'reseller_id' => $locked->id,
                'entry_type' => $entryType,
                'direction' => $direction,
                'amount' => $amount,
                'admin_receivable_after' => $newDue,
                'reference' => $refPrefix.'-'.now()->format('YmdHis'),
                'notes' => $notes,
                'created_by' => $by?->id,
            ]);

            $locked->forceFill(['admin_receivable_due' => $newDue])->saveQuietly();

            return $entry;
        });
    }

    /**
     * @return array{
     *     admin_due: float,
     *     margin_total: float,
     *     credit_limit: float,
     *     utilization_percent: float,
     *     status: string,
     *     total_wholesale_accrued: float,
     *     paid_to_hq_settlement: float,
     *     reduced_from_customer_payments: float,
     *     total_credits: float
     * }
     */
    public function summary(Reseller $reseller): array
    {
        $due = (float) $reseller->admin_receivable_due;
        $limit = max(0, (float) $reseller->credit_limit);
        $util = $limit > 0 ? min(999, round(($due / $limit) * 100, 1)) : 0;

        $status = 'ok';
        if ($limit > 0 && $due >= $limit) {
            $status = 'breach';
        } elseif ($limit > 0 && $util >= (int) config('reseller_billing.automation.warning_threshold_percent', 80)) {
            $status = 'warning';
        }

        $breakdown = $this->ledgerBreakdown($reseller);

        return [
            'admin_due' => $due,
            'margin_total' => (float) $reseller->margin_accrued_total,
            'credit_limit' => $limit,
            'utilization_percent' => $util,
            'status' => $status,
            ...$breakdown,
        ];
    }

    /**
     * @return array{
     *     total_wholesale_accrued: float,
     *     paid_to_hq_settlement: float,
     *     reduced_from_customer_payments: float,
     *     credit_notes: float,
     *     debit_notes: float,
     *     total_credits: float,
     *     calculated_due: float
     * }
     */
    public function ledgerBreakdown(Reseller $reseller): array
    {
        $base = ResellerLedgerEntry::query()->where('reseller_id', $reseller->id);

        $accrued = (float) (clone $base)
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL)
            ->where('direction', ResellerLedgerEntry::DIRECTION_DEBIT)
            ->sum('amount');

        $debitNotes = (float) (clone $base)
            ->where('entry_type', ResellerLedgerEntry::TYPE_DEBIT_NOTE)
            ->where('direction', ResellerLedgerEntry::DIRECTION_DEBIT)
            ->sum('amount');

        $paidSettlement = (float) (clone $base)
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_SETTLEMENT)
            ->where('direction', ResellerLedgerEntry::DIRECTION_CREDIT)
            ->sum('amount');

        $fromCollections = (float) (clone $base)
            ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_COLLECTION)
            ->where('direction', ResellerLedgerEntry::DIRECTION_CREDIT)
            ->sum('amount');

        $creditNotes = (float) (clone $base)
            ->where('entry_type', ResellerLedgerEntry::TYPE_CREDIT_NOTE)
            ->where('direction', ResellerLedgerEntry::DIRECTION_CREDIT)
            ->sum('amount');

        $totalDebits = round($accrued + $debitNotes, 2);
        $totalCredits = round($paidSettlement + $fromCollections + $creditNotes, 2);
        $calculatedDue = max(0, round($totalDebits - $totalCredits, 2));

        return [
            'total_wholesale_accrued' => round($accrued, 2),
            'debit_notes' => round($debitNotes, 2),
            'paid_to_hq_settlement' => round($paidSettlement, 2),
            'reduced_from_customer_payments' => round($fromCollections, 2),
            'credit_notes' => round($creditNotes, 2),
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'calculated_due' => $calculatedDue,
        ];
    }

    /**
     * Retail side: what subscribers owe the reseller (invoices vs payments vs discounts).
     *
     * @return array{invoiced: float, collected: float, discounted: float, due: float}
     */
    public function customerDueBreakdown(Reseller $reseller): array
    {
        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return ['invoiced' => 0.0, 'collected' => 0.0, 'discounted' => 0.0, 'due' => 0.0];
        }

        $invoices = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotIn('status', ['void', 'cancelled'])
            ->get(['total', 'amount_paid', 'discount_amount']);

        $invoiced = round($invoices->sum(fn ($i) => (float) $i->total), 2);
        $collected = round($invoices->sum(fn ($i) => (float) $i->amount_paid), 2);
        $discounted = round($invoices->sum(fn ($i) => (float) $i->discount_amount), 2);
        $due = round($invoices->sum(fn ($i) => max(0, (float) $i->total - (float) $i->amount_paid)), 2);

        return [
            'invoiced' => $invoiced,
            'collected' => $collected,
            'discounted' => $discounted,
            'due' => $due,
        ];
    }

    /**
     * One row per subscriber — retail due + HQ wholesale line for the current month.
     *
     * @return list<array{
     *     customer_id: int,
     *     customer_code: string,
     *     name: string,
     *     status: string,
     *     invoice_number: ?string,
     *     retail_total: float,
     *     retail_due: float,
     *     wholesale: float,
     *     has_accrual: bool
     * }>
     */
    public function subscriberDueLines(Reseller $reseller, ?Carbon $month = null): array
    {
        $month ??= now();
        $lines = [];

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('reseller_id', $reseller->id)
            ->orderBy('customer_code')
            ->get(['id', 'customer_code', 'name', 'status']);

        foreach ($customers as $customer) {
            $invoice = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', ['void', 'cancelled'])
                ->whereYear('issue_date', $month->year)
                ->whereMonth('issue_date', $month->month)
                ->latest('id')
                ->first(['id', 'invoice_number', 'total', 'amount_paid']);

            $accrual = ResellerLedgerEntry::query()
                ->where('reseller_id', $reseller->id)
                ->where('customer_id', $customer->id)
                ->where('entry_type', ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->latest('id')
                ->first(['amount']);

            $pause = app(ResellerSuspendedBillingService::class);
            $paused = $pause->isBillingPaused($customer);
            $showPausedMonth = $paused && $pause->isSuspensionMonthStillCurrent($customer);
            $inPauseMonth = $invoice && $showPausedMonth
                && $invoice->issue_date
                && \Carbon\Carbon::parse($invoice->issue_date)->isSameMonth($pause->suspensionMonthStart($customer) ?? now());
            $showBill = ! $paused || $inPauseMonth;
            $retailTotal = $invoice && $showBill ? (float) $invoice->total : 0.0;
            $retailDue = $invoice && $showBill
                ? max(0, round((float) $invoice->total - (float) $invoice->amount_paid, 2))
                : 0.0;
            $wholesale = $showBill && $accrual ? round((float) $accrual->amount, 2) : 0.0;

            $lines[] = [
                'customer_id' => (int) $customer->id,
                'customer_code' => (string) $customer->customer_code,
                'name' => (string) $customer->name,
                'status' => (string) $customer->status,
                'invoice_number' => $showBill ? $invoice?->invoice_number : null,
                'retail_total' => round($retailTotal, 2),
                'retail_due' => $retailDue,
                'wholesale' => $wholesale,
                'has_accrual' => $showBill && $accrual !== null,
            ];
        }

        return $lines;
    }

    public static function entryTypeLabel(string $entryType): string
    {
        return match ($entryType) {
            ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL => 'Wholesale bill (+due)',
            ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_SETTLEMENT => 'আপনি HQ-তে জমা (−due)',
            ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_COLLECTION => 'Customer payment (−due)',
            ResellerLedgerEntry::TYPE_CREDIT_NOTE => 'Credit note / ছাড় (−due)',
            ResellerLedgerEntry::TYPE_DEBIT_NOTE => 'Debit note (+due)',
            ResellerLedgerEntry::TYPE_MARGIN_ACCRUAL => 'Margin',
            default => str_replace('_', ' ', $entryType),
        };
    }
}
