<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CustomerBalanceDue;

/**
 * Drops duplicate local open bills so per-subscriber due matches legacy portal BalanceDue.
 */
final class LegacyPortalInvoiceAligner
{
    public function voidStaleOpenInvoices(Customer $customer, string $periodKey): int
    {
        $code = (string) $customer->customer_code;
        $keep = array_filter([
            'ISD-'.$code.'-'.$periodKey,
            'ISD-'.$code.'-PRIOR-BALANCE',
        ]);

        $voided = 0;

        Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->where(function ($q): void {
                $q->where('invoice_number', 'like', 'ISD-%')
                    ->orWhere('invoice_number', 'like', rtrim((string) config('billing.invoice_number_prefix', 'INV'), '-').'-%');
            })
            ->whereNotIn('invoice_number', $keep)
            ->orderBy('issue_date')
            ->each(function (Invoice $invoice) use (&$voided): void {
                $voided++;
                $invoice->updateTrusted([
                    'status' => 'void',
                    'notes' => trim(($invoice->notes ?? '').' · Voided — legacy portal billing sync (duplicate/stale)'),
                ]);
            });

        return $voided;
    }

    public function voidAllOpenInvoicesWhenIspDueZero(Customer $customer, float $ispBalanceDue): int
    {
        if ($ispBalanceDue > 0.009) {
            return 0;
        }

        $voided = 0;
        Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->each(function (Invoice $invoice) use (&$voided): void {
                $voided++;
                $invoice->updateTrusted([
                    'status' => 'void',
                    'notes' => trim(($invoice->notes ?? '').' · Voided — no balance on legacy portal billing grid'),
                ]);
            });

        return $voided;
    }
}
