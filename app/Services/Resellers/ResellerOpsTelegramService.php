<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Services\Notifications\Channels\TelegramNotificationChannel;
use App\Support\NotificationChannel;

final class ResellerOpsTelegramService
{
    public function isEnabled(): bool
    {
        return (bool) config('notifications.telegram.enabled', false)
            && filled(config('notifications.telegram.ops_chat_id'))
            && (bool) config('reseller_billing.telegram_ops_alerts', true);
    }

    public function alert(int $tenantId, string $title, string $body): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $chatId = (string) config('notifications.telegram.ops_chat_id', '');
        $message = trim($title."\n\n".$body);

        try {
            $channel = new TelegramNotificationChannel;
            if ($channel->isEnabled()) {
                $channel->send($chatId, $message, ['tenant_id' => $tenantId]);
            }
        } catch (\Throwable) {
            // non-blocking
        }
    }

    public function paymentCollected(Reseller $reseller, string $customerCode, float $amount, string $mode): void
    {
        $this->alert(
            (int) $reseller->tenant_id,
            'Reseller payment · '.$reseller->code,
            sprintf(
                "Partner: %s\nCustomer: %s\nAmount: %s BDT\nMode: %s",
                $reseller->name,
                $customerCode,
                number_format($amount, 2),
                $mode,
            ),
        );
    }

    public function dueReminderSent(Reseller $reseller, string $customerCode, string $invoiceNumber, float $balance): void
    {
        $this->alert(
            (int) $reseller->tenant_id,
            'Due reminder sent · '.$reseller->code,
            sprintf(
                "Partner: %s\nCustomer: %s\nBill: %s\nDue: %s BDT",
                $reseller->name,
                $customerCode,
                $invoiceNumber,
                number_format($balance, 2),
            ),
        );
    }
}
