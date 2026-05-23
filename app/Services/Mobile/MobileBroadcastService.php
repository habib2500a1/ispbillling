<?php

namespace App\Services\Mobile;

use App\Events\Mobile\OnuSignalEvent;
use App\Events\Mobile\PaymentReceivedEvent;
use App\Events\Mobile\ResellerPartnerEvent;
use App\Events\Mobile\RouterAlertEvent;
use App\Models\Customer;
use App\Models\Payment;

final class MobileBroadcastService
{
    public function paymentReceived(Payment $payment): void
    {
        event(new PaymentReceivedEvent($payment));

        $resellerId = (int) ($payment->customer?->reseller_id ?? 0);
        if ($resellerId > 0) {
            event(new ResellerPartnerEvent($payment, $resellerId, 'payment_received'));
        }
    }

    public function onuSignalChanged(int $tenantId, int $deviceId, array $snapshot): void
    {
        event(new OnuSignalEvent($tenantId, $deviceId, $snapshot));
    }

    public function routerAlert(int $tenantId, string $message, array $meta = []): void
    {
        event(new RouterAlertEvent($tenantId, $message, $meta));
    }

    public function notifyCustomerDueReminder(Customer $customer): void
    {
        $due = $customer->openInvoiceBalance();
        if ($due <= 0) {
            return;
        }

        app(PushNotificationService::class)->sendTo(
            $customer,
            'customer',
            'Bill reminder',
            'Your due amount is '.number_format($due, 2).' BDT. Pay from the app.',
            ['type' => 'due_reminder', 'amount' => $due],
        );
    }
}
