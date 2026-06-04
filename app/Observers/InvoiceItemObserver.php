<?php

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Services\Billing\InvoiceCalculator;

class InvoiceItemObserver
{
    public function saved(InvoiceItem $invoiceItem): void
    {
        $invoice = $invoiceItem->relationLoaded('invoice')
            ? $invoiceItem->invoice
            : $invoiceItem->invoice()->withoutGlobalScopes()->first();

        if ($invoice !== null) {
            InvoiceCalculator::recalculate($invoice);
        }
    }

    public function deleted(InvoiceItem $invoiceItem): void
    {
        if ($invoiceItem->invoice) {
            InvoiceCalculator::recalculate($invoiceItem->invoice);
        }
    }
}
