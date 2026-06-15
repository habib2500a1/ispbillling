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
        $type = $payment->payment_type ?? PaymentType::PAYMENT;
        if (! in_array($type, [PaymentType::PAYMENT, PaymentType::PREPAY], true)) {
            return;
        }

        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $event = CollectionPaymentClassifier::notificationEvent($payment);
        $vars = CollectionPaymentClassifier::notificationVariables($payment);
        $meta = is_array($payment->meta) ? $payment->meta : [];

        if (! ($meta['skip_customer_sms'] ?? false)) {
            $this->dispatcher->notifyCustomer($customer, $event, $vars, [
                'subject' => ($event === \App\Support\NotificationEvent::PAYMENT_ADVANCE
                    ? 'Advance received'
                    : 'Payment received').' — '.$customer->name,
            ]);
        }

        $this->ops->onPaymentCompleted($payment);
    }
}
