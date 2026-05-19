<?php

namespace App\Services\Notifications;

use App\Models\Payment;
use App\Support\NotificationEvent;
use App\Support\PaymentType;

final class PaymentNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function onPaymentCompleted(Payment $payment): void
    {
        if (! in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true)) {
            return;
        }

        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $invoice = $payment->invoice;

        $this->dispatcher->notifyCustomer($customer, NotificationEvent::PAYMENT_SUCCESS, [
            'amount' => number_format((float) $payment->amount, 2),
            'invoice_number' => $invoice?->invoice_number ?? '—',
            'receipt_number' => $payment->receipt_number ?? '—',
        ], [
            'subject' => 'Payment received — '.$customer->name,
        ]);
    }
}
