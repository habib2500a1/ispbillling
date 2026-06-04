<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Models\ResellerLedgerEntry;
use App\Support\CustomerStatus;
use Carbon\Carbon;

/**
 * Suspended billing rules:
 * - Bill generate → same month suspend: that month's bill stays (due visible until month ends).
 * - Next month still suspended: no bill, no due shown, no auto-generation.
 * - Reactivate: clear old opens, one fresh activation bill.
 */
final class ResellerSuspendedBillingService
{
    public function markSuspended(Customer $customer): void
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['suspended_at'] = now()->toIso8601String();
        $meta['billing_paused'] = true;
        $customer->forceFill(['meta' => $meta])->saveQuietly();

        // Only void open bills from months BEFORE the suspend month (keep this month's bill).
        $this->voidOpenInvoicesForPause($customer, 'voided_while_suspended', keepSuspensionMonth: true);
    }

    public function clearPauseState(Customer $customer): void
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        unset($meta['billing_paused'], $meta['suspended_at']);
        $customer->forceFill(['meta' => $meta])->saveQuietly();
    }

    public function isBillingPaused(Customer $customer): bool
    {
        if (CustomerStatus::normalize((string) $customer->status) === CustomerStatus::SUSPENDED) {
            return true;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];

        return (bool) ($meta['billing_paused'] ?? false);
    }

    /**
     * Suspend month only: show due for bills issued in the month the customer was suspended.
     * After calendar month rolls over while still suspended → no bill / zero due.
     */
    public function isSuspensionMonthStillCurrent(Customer $customer): bool
    {
        $anchor = $this->suspensionMonthStart($customer);

        return $anchor !== null && now()->isSameMonth($anchor);
    }

    public function voidStaleOpenInvoicesBeforeActivation(Customer $customer): int
    {
        return $this->voidOpenInvoicesForPause($customer, 'voided_on_reactivation', keepSuspensionMonth: false);
    }

    public function displayableOpenDue(Customer $customer): float
    {
        if (! $this->isBillingPaused($customer)) {
            return $customer->openInvoiceBalance();
        }

        if (! $this->isSuspensionMonthStillCurrent($customer)) {
            return 0.0;
        }

        return $this->openDueForSuspensionMonth($customer);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    public function invoicesQueryWhileSuspended(Customer $customer): \Illuminate\Database\Eloquent\Builder
    {
        $query = Invoice::query()->where('customer_id', $customer->id);

        if (! $this->isBillingPaused($customer) || ! $this->isSuspensionMonthStillCurrent($customer)) {
            return $query->whereRaw('1 = 0');
        }

        $anchor = $this->suspensionMonthStart($customer);

        return $query
            ->whereNotIn('status', ['void', 'cancelled'])
            ->whereYear('issue_date', $anchor->year)
            ->whereMonth('issue_date', $anchor->month);
    }

    public function openDueForSuspensionMonth(Customer $customer): float
    {
        $anchor = $this->suspensionMonthStart($customer);
        if ($anchor === null) {
            return 0.0;
        }

        $sum = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->whereYear('issue_date', $anchor->year)
            ->whereMonth('issue_date', $anchor->month)
            ->get(['total', 'amount_paid'])
            ->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->total - (float) $invoice->amount_paid));

        return round((float) $sum, 2);
    }

    public function suspensionMonthStart(Customer $customer): ?Carbon
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $at = $meta['suspended_at'] ?? null;
        if (! filled($at)) {
            return null;
        }

        return Carbon::parse($at)->startOfMonth();
    }

    private function isInvoiceInSuspensionMonth(Invoice $invoice, Customer $customer): bool
    {
        $anchor = $this->suspensionMonthStart($customer);
        if ($anchor === null || $invoice->issue_date === null) {
            return false;
        }

        return Carbon::parse($invoice->issue_date)->isSameMonth($anchor);
    }

    private function voidOpenInvoicesForPause(Customer $customer, string $reason, bool $keepSuspensionMonth): int
    {
        $customer->loadMissing('reseller');
        $reseller = $customer->reseller;
        $voided = 0;

        $open = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->get();

        foreach ($open as $invoice) {
            if ($keepSuspensionMonth && $this->isInvoiceInSuspensionMonth($invoice, $customer)) {
                continue;
            }

            if ($reseller !== null) {
                $this->reverseWholesaleAccrualIfAny($reseller, $invoice, $reason);
            }

            $meta = is_array($invoice->meta) ? $invoice->meta : [];
            $meta['void_reason'] = $reason;
            $meta['voided_at'] = now()->toIso8601String();

            $invoice->forceFill([
                'status' => 'void',
                'meta' => $meta,
            ])->saveQuietly();

            $voided++;
        }

        return $voided;
    }

    private function reverseWholesaleAccrualIfAny(Reseller $reseller, Invoice $invoice, string $reason): void
    {
        if (! app(ResellerDueLedgerService::class)->usesPostpaidDue($reseller)) {
            return;
        }

        $reference = 'INV-ACCR-'.$invoice->id;
        $entry = ResellerLedgerEntry::query()
            ->where('reseller_id', $reseller->id)
            ->where('reference', $reference)
            ->first();

        if ($entry === null) {
            return;
        }

        $reverseRef = 'INV-ACCR-REV-'.$invoice->id;
        if (ResellerLedgerEntry::query()->where('reseller_id', $reseller->id)->where('reference', $reverseRef)->exists()) {
            return;
        }

        app(ResellerDueLedgerService::class)->recordCreditNote(
            $reseller,
            (float) $entry->amount,
            null,
            sprintf('Reversed wholesale for %s (%s)', $invoice->invoice_number, $reason),
        );

        ResellerLedgerEntry::query()
            ->where('id', $entry->id)
            ->update(['reference' => $reference.'-voided']);
    }
}
