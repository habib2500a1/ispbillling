<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Accounting\AccountingIntegrationService;
use App\Services\BillPayment\PaymentLinkService;
use App\Services\Collector\CollectorSettlementService;
use App\Services\Notifications\PaymentNotificationService;
use App\Services\Payments\PaymentProcessor;
use App\Services\Mobile\MobileBroadcastService;
use App\Services\Resellers\ResellerCommissionService;

class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        if ($payment->status !== 'completed') {
            return;
        }

        $becameCompleted = $payment->wasRecentlyCreated
            || ($payment->wasChanged('status')
                && $payment->getOriginal('status') !== 'completed'
                && $payment->status === 'completed');

        if (! $becameCompleted) {
            return;
        }

        PaymentProcessor::processCompletedPayment($payment);

        $payment->refresh();
        app(PaymentNotificationService::class)->onPaymentCompleted($payment);
        app(ResellerCommissionService::class)->accrueFromPayment($payment);
        app(AccountingIntegrationService::class)->postCustomerPayment($payment);
        app(CollectorSettlementService::class)->recordCollectionFromPayment($payment->fresh());
        app(PaymentLinkService::class)->markConverted($payment->invoice_id, (int) $payment->id);
        app(MobileBroadcastService::class)->paymentReceived($payment->fresh());
    }
}
