<?php

namespace App\Observers;

use App\Models\BillingAuditLog;
use App\Models\Invoice;
use App\Services\Accounting\AccountingIntegrationService;
use App\Services\Billing\PackageUpgradeApplicator;
use App\Support\CustomerBalanceDue;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
            app(AccountingIntegrationService::class)->postIssuedInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice): void
    {
        $keys = [
            'subtotal', 'total', 'status', 'tax_amount', 'sd_amount', 'withholding_amount',
            'discount_amount', 'coupon_discount_amount', 'due_date', 'amount_paid',
        ];
        $changed = false;
        foreach ($keys as $k) {
            if ($invoice->wasChanged($k)) {
                $changed = true;
                break;
            }
        }
        if (! $changed) {
            return;
        }

        BillingAuditLog::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => auth('web')->id(),
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'event' => 'invoice_updated',
            'properties' => [
                'changes' => array_intersect_key($invoice->getChanges(), array_flip($keys)),
            ],
        ]);

        if ($invoice->wasChanged('status') && in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
            app(AccountingIntegrationService::class)->postIssuedInvoice($invoice);
        }

        if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
            app(PackageUpgradeApplicator::class)->applyWhenInvoicePaid($invoice->fresh(['customer', 'items']));
        }

        if ($invoice->customer_id && ($invoice->wasChanged('status') || $invoice->wasChanged('amount_paid'))) {
            $customer = $invoice->customer?->fresh();
            if ($customer !== null) {
                CustomerBalanceDue::refreshMetaAfterPayment($customer);
            }
        }
    }
}
