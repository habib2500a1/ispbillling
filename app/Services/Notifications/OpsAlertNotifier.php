<?php

namespace App\Services\Notifications;

use App\Support\NotificationEvent;

final class OpsAlertNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function pendingGatewayPayment(int $tenantId, string $gateway, string $trxId, float $amount, string $customerName): void
    {
        if (! config('alerts.pending_payment_enabled', true)) {
            return;
        }

        $this->dispatcher->notifyOps($tenantId, NotificationEvent::PENDING_GATEWAY_PAYMENT, [
            'gateway' => strtoupper($gateway),
            'transaction_id' => $trxId,
            'amount' => number_format($amount, 2),
            'name' => $customerName,
        ]);
    }

    public function sessionIntegrity(int $tenantId, string $alertType, string $login, string $message): void
    {
        if (! config('alerts.session_integrity_enabled', true)) {
            return;
        }

        $this->dispatcher->notifyOps($tenantId, NotificationEvent::SESSION_INTEGRITY, [
            'alert_type' => str_replace('_', ' ', $alertType),
            'login' => $login,
            'message' => $message,
        ]);
    }
}
