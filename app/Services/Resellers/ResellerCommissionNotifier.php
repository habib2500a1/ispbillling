<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

final class ResellerCommissionNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function notifyAccrued(ResellerCommission $commission): void
    {
        if (! config('notifications.events.reseller_commission.enabled', true)) {
            return;
        }

        $reseller = $commission->reseller;
        if (! $reseller instanceof Reseller) {
            return;
        }

        $this->send($reseller, 'reseller_commission_accrued', [
            'amount' => number_format((float) $commission->commission_amount, 2),
            'gross' => number_format((float) $commission->gross_amount, 2),
            'code' => $reseller->code ?? '',
        ]);

        app(ResellerPortalNotifier::class)->commissionAccrued(
            $reseller,
            (float) $commission->commission_amount,
            $commission->customer?->customer_code ?? 'subscriber',
        );
    }

    public function notifyPaid(ResellerCommission $commission): void
    {
        if (! config('notifications.events.reseller_commission_payout.enabled', true)) {
            return;
        }

        $reseller = $commission->reseller;
        if (! $reseller instanceof Reseller) {
            return;
        }

        $this->send($reseller, 'reseller_commission_paid', [
            'amount' => number_format((float) $commission->commission_amount, 2),
            'code' => $reseller->code ?? '',
        ]);

        app(ResellerPortalNotifier::class)->walletCredited(
            $reseller,
            (float) $commission->commission_amount,
            'Commission payout',
        );
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function send(Reseller $reseller, string $eventKey, array $variables): void
    {
        $phone = trim((string) ($reseller->phone ?? ''));
        $email = trim((string) ($reseller->email ?? ''));

        $message = $this->message($eventKey, $variables);
        $tenantId = (int) $reseller->tenant_id;

        try {
            if ($phone !== '' && config('notifications.sms.enabled', false)) {
                $this->dispatcher->send(
                    $tenantId,
                    null,
                    $eventKey,
                    \App\Support\NotificationChannel::SMS,
                    $phone,
                    $message,
                );
            }
            if ($email !== '' && config('notifications.email.enabled', true)) {
                $this->dispatcher->send(
                    $tenantId,
                    null,
                    $eventKey,
                    \App\Support\NotificationChannel::EMAIL,
                    $email,
                    $message,
                    ['subject' => $this->subject($eventKey)],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('reseller.commission.notify_failed', [
                'reseller_id' => $reseller->id,
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function message(string $eventKey, array $variables): string
    {
        $tpl = (string) config("notifications.templates.{$eventKey}", '');
        if ($tpl === '') {
            $tpl = $eventKey === 'reseller_commission_paid'
                ? 'Commission {amount} BDT paid to your reseller wallet. Code: {code}'
                : 'Commission {amount} BDT earned (payment {gross} BDT). Code: {code}';
        }

        foreach ($variables as $key => $value) {
            $tpl = str_replace('{'.$key.'}', $value, $tpl);
        }

        return $tpl;
    }

    private function subject(string $eventKey): string
    {
        return $eventKey === 'reseller_commission_paid'
            ? 'Reseller commission paid'
            : 'Reseller commission earned';
    }
}
