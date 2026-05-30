<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerPortalNotification;

final class ResellerPortalNotifier
{
    public function notify(Reseller $reseller, string $type, string $title, ?string $body = null, array $meta = []): ResellerPortalNotification
    {
        return ResellerPortalNotification::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    public function paymentReceived(Reseller $reseller, string $customerCode, float $amount, ?int $paymentId = null): void
    {
        $this->notify(
            $reseller,
            'payment_received',
            'Payment collected',
            number_format($amount, 2).' BDT from '.$customerCode,
            array_filter(['payment_id' => $paymentId]),
        );
    }

    public function settlementSubmitted(Reseller $reseller, float $amount, string $settlementNumber): void
    {
        $this->notify(
            $reseller,
            'settlement_submitted',
            'Settlement submitted',
            number_format($amount, 2).' BDT · '.$settlementNumber,
        );
    }

    public function commissionAccrued(Reseller $reseller, float $amount, string $customerCode): void
    {
        $this->notify(
            $reseller,
            'commission_accrued',
            'Commission earned',
            number_format($amount, 2).' BDT from '.$customerCode,
        );
    }

    public function walletCredited(Reseller $reseller, float $amount, ?string $reference = null): void
    {
        $this->notify(
            $reseller,
            'wallet_credited',
            'Wallet credited',
            number_format($amount, 2).' BDT'.($reference ? ' · '.$reference : ''),
        );
    }

    public function dueReminder(Reseller $reseller, int $dueCustomers, float $dueAmount, int $expiringCustomers = 0): void
    {
        $parts = [];

        if ($dueCustomers > 0 && $dueAmount > 0) {
            $parts[] = sprintf(
                '%d subscriber(s) · %s BDT outstanding',
                $dueCustomers,
                number_format($dueAmount, 0),
            );
        }

        if ($expiringCustomers > 0) {
            $days = max(1, (int) config('automation.reseller_due_reminders.expiring_within_days', 3));
            $parts[] = sprintf(
                '%d expire within %d day(s)',
                $expiringCustomers,
                $days,
            );
        }

        $body = $parts !== [] ? implode(' · ', $parts).'. Collect or renew before auto-suspend.' : null;

        $this->notify(
            $reseller,
            'due_reminder',
            'Due collection reminder',
            $body,
            array_filter([
                'due_customers' => $dueCustomers > 0 ? $dueCustomers : null,
                'due_amount' => $dueAmount > 0 ? round($dueAmount, 2) : null,
                'expiring_customers' => $expiringCustomers > 0 ? $expiringCustomers : null,
                'reminder_date' => now()->toDateString(),
            ]),
        );
    }

    public function walletRechargeSubmitted(Reseller $reseller, float $amount, string $requestNumber): void
    {
        $this->notify(
            $reseller,
            'wallet_recharge_submitted',
            'Wallet top-up submitted',
            number_format($amount, 2).' BDT · '.$requestNumber.' awaiting admin approval',
            ['request_number' => $requestNumber],
        );
    }

    public function walletRechargeRejected(Reseller $reseller, float $amount, string $requestNumber, string $reason): void
    {
        $this->notify(
            $reseller,
            'wallet_recharge_rejected',
            'Wallet top-up rejected',
            number_format($amount, 2).' BDT · '.$requestNumber.' — '.$reason,
            ['request_number' => $requestNumber],
        );
    }

    public function unreadCount(Reseller $reseller): int
    {
        return (int) ResellerPortalNotification::query()
            ->where('reseller_id', $reseller->id)
            ->whereNull('read_at')
            ->count();
    }
}
