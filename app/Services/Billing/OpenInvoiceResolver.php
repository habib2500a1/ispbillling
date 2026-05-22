<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CustomerBalanceDue;
use Illuminate\Support\Collection;

final class OpenInvoiceResolver
{
    /**
     * Open invoices with balance due, oldest first (FIFO bill pay).
     *
     * @return Collection<int, Invoice>
     */
    public static function openInvoicesWithBalance(Customer $customer): Collection
    {
        return Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->balanceDue() > 0.009)
            ->values();
    }

    public static function totalOpenDue(Customer $customer): float
    {
        return round(
            (float) self::openInvoicesWithBalance($customer)->sum(fn (Invoice $i): float => $i->balanceDue()),
            2,
        );
    }

    public static function forCustomer(Customer $customer, ?int $invoiceId = null): ?Invoice
    {
        if ($invoiceId !== null && $invoiceId > 0) {
            return Invoice::withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->whereKey($invoiceId)
                ->first();
        }

        return Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->first(fn (Invoice $invoice): bool => $invoice->balanceDue() > 0.009);
    }
}
