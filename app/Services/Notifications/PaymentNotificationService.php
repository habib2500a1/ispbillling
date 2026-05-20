<?php

namespace App\Services\Notifications;

use App\Models\Payment;
use App\Services\Billing\CollectionPaymentClassifier;
use App\Support\PaymentType;

final class PaymentNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly OpsNotificationService $ops,
    ) {}

    public function onPaymentCompleted(Payment $payment): void
    {
        // Customer + ops alerts only for real collections (not wallet_apply / refunds).
        if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PAYMENT) {
            return;
        }

        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $event = CollectionPaymentClassifier::notificationEvent($payment);
        $vars = CollectionPaymentClassifier::notificationVariables($payment);

        $this->dispatcher->notifyCustomer($customer, $event, $vars, [
            'subject' => ($event === \App\Support\NotificationEvent::PAYMENT_ADVANCE
                ? 'Advance received'
                : 'Payment received').' — '.$customer->name,
        ]);

        $this->ops->onPaymentCompleted($payment);
    }
}
