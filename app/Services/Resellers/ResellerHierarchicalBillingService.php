<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ResellerBillingSettlementMode;

/**
 * Orchestrates wholesale settlement: postpaid due ledger vs prepaid wallet vs hybrid.
 */
final class ResellerHierarchicalBillingService
{
    public function isEnabled(): bool
    {
        return (bool) config('reseller_billing.hierarchical_enabled', true);
    }

    public function handleInvoiceCreated(Invoice $invoice): void
    {
        if (! $this->isEnabled()) {
            app(ResellerWholesaleDebitService::class)->debitForInvoice($invoice);

            return;
        }

        $invoice->loadMissing('customer.reseller');
        $reseller = $invoice->customer?->reseller;
        if ($reseller === null) {
            return;
        }

        $mode = $reseller->billing_settlement_mode
            ?? config('reseller_billing.default_settlement_mode', ResellerBillingSettlementMode::POSTPAID_DUE);

        if ($mode === ResellerBillingSettlementMode::WALLET_PREPAID) {
            app(ResellerWholesaleDebitService::class)->debitForInvoice($invoice);

            return;
        }

        if ($mode === ResellerBillingSettlementMode::POSTPAID_DUE) {
            app(ResellerDueLedgerService::class)->accrueFromInvoice($invoice);

            return;
        }

        $customer = $invoice->customer;
        $wholesale = app(ResellerWholesaleDebitService::class)->resolveAmount($invoice, $customer);
        if ($wholesale <= 0) {
            return;
        }

        $result = app(ResellerWholesaleDebitService::class)->debitForInvoice($invoice);
        $debited = $result['debited'] ? (float) $result['amount'] : 0.0;
        $remainder = max(0, round($wholesale - $debited, 2));

        if ($remainder > 0) {
            app(ResellerDueLedgerService::class)->accrueFromInvoice($invoice, $remainder);
        }
    }

    public function handlePaymentCompleted(Payment $payment): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        app(ResellerDueLedgerService::class)->applyCustomerPayment($payment);
    }
}
