<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\CarbonInterface;

/**
 * Late fees apply after due_date + grace_period_days on outstanding balance.
 */
final class LateFeeCalculator
{
    public static function graceEndsAt(Invoice $invoice, ?Customer $customer = null): \Carbon\Carbon
    {
        $customer ??= $invoice->customer;
        $grace = max(0, (int) ($customer?->grace_period_days ?? 0));

        return $invoice->due_date->copy()->addDays($grace)->endOfDay();
    }

    public static function isPastGrace(Invoice $invoice, ?CarbonInterface $asOf = null): bool
    {
        $asOf ??= now();

        if (! in_array($invoice->status, ['open', 'partial'], true)) {
            return false;
        }

        return $asOf->gt(static::graceEndsAt($invoice));
    }

    public static function daysLateAfterGrace(Invoice $invoice, ?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $graceEnd = static::graceEndsAt($invoice)->startOfDay();
        if ($asOf->toDateString() <= $graceEnd->toDateString()) {
            return 0;
        }

        return max(0, $graceEnd->diffInDays($asOf->startOfDay()));
    }

    public static function outstandingBalance(Invoice $invoice): float
    {
        return max(0.0, round((float) $invoice->total - (float) $invoice->amount_paid, 2));
    }

    /**
     * Compute late fee for one application (not yet on invoice).
     */
    public static function calculateFee(Invoice $invoice, ?CarbonInterface $asOf = null): float
    {
        $customer = $invoice->customer;
        if ($customer === null || ! static::isPastGrace($invoice, $asOf)) {
            return 0.0;
        }

        $balance = static::outstandingBalance($invoice);
        if ($balance <= 0) {
            return 0.0;
        }

        $daysLate = static::daysLateAfterGrace($invoice, $asOf);
        $period = $customer->late_fee_period ?? 'daily';
        $periods = $period === 'weekly'
            ? (int) max(1, ceil($daysLate / 7))
            : max(1, $daysLate);

        $fixed = (float) ($customer->late_fee_fixed ?? 0);
        $percent = (float) ($customer->late_fee_percent ?? 0);

        $fee = ($periods * $fixed) + ($balance * ($percent / 100));

        return round(max(0, $fee), 2);
    }

    /**
     * Add or update a late_fee line item and recalculate totals.
     */
    public static function applyToInvoice(Invoice $invoice, ?CarbonInterface $asOf = null): bool
    {
        $fee = static::calculateFee($invoice, $asOf);
        if ($fee <= 0) {
            return false;
        }

        $invoice->load('items');
        $existing = $invoice->items->firstWhere('item_type', 'late_fee');

        if ($existing) {
            if ((float) $existing->unit_price === $fee) {
                return false;
            }
            $existing->update([
                'description' => 'Late payment fee',
                'unit_price' => $fee,
                'quantity' => 1,
            ]);
        } else {
            $invoice->items()->create([
                'item_type' => 'late_fee',
                'description' => 'Late payment fee',
                'quantity' => 1,
                'unit_price' => $fee,
                'line_total' => 0,
                'sort_order' => 999,
                'meta' => [
                    'days_late' => static::daysLateAfterGrace($invoice, $asOf),
                    'applied_at' => ($asOf ?? now())->toIso8601String(),
                ],
            ]);
        }

        InvoiceCalculator::recalculate($invoice->fresh());

        return true;
    }
}
