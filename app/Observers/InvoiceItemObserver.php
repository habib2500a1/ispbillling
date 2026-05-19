<?php

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Services\Billing\InvoiceCalculator;

class InvoiceItemObserver
{
    public function saved(InvoiceItem $invoiceItem): void
    {
        InvoiceCalculator::recalculate($invoiceItem->invoice);
    }

    public function deleted(InvoiceItem $invoiceItem): void
    {
        if ($invoiceItem->invoice) {
            InvoiceCalculator::recalculate($invoiceItem->invoice);
        }
    }
}
