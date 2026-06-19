<?php

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Log;

final class AutomatedSmsNotifier
{
    public function __construct(
        private readonly SmsTemplateService $templates,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function onClientCreated(Customer $customer): void
    {
        if (! $this->customerWantsSms($customer)) {
            return;
        }

        $this->sendToCustomer($customer, 'client_created', SmsTemplateVariableBuilder::forCustomer($customer));
    }

    public function onClientStatusChanged(Customer $customer, string $from, string $to): void
    {
        if (! $this->customerWantsSms($customer)) {
            return;
        }

        $key = SmsTemplateVariableBuilder::statusEventKey($customer, $from, $to);
        if ($key === null) {
            return;
        }

        $this->sendToCustomer($customer, $key, SmsTemplateVariableBuilder::forCustomer($customer));
    }

    public function onNetworkAccessChanged(Customer $customer, string $from, string $to): void
    {
        if (! $this->customerWantsSms($customer) || $from === $to) {
            return;
        }

        if ($to === 'suspended') {
            $this->sendToCustomer($customer, 'client_disable', SmsTemplateVariableBuilder::forCustomer($customer));

            return;
        }

        if ($from === 'suspended' && $to === 'active') {
            $this->sendToCustomer($customer, 'client_enable', SmsTemplateVariableBuilder::forCustomer($customer));
        }
    }

    public function onPaymentCompleted(Payment $payment): void
    {
        if ($payment->customer === null) {
            return;
        }

        $this->sendToCustomer(
            $payment->customer,
            NotificationEvent::PAYMENT_SUCCESS,
            SmsTemplateVariableBuilder::forPayment($payment),
        );
    }

    public function onSupportTicketCreated(SupportTicket $ticket): void
    {
        if ($ticket->customer !== null) {
            $this->notifyCustomerEvent(
                $ticket->customer,
                'support_token_created',
                SmsTemplateVariableBuilder::forTicket($ticket),
            );
        }
    }

    public function onSupportTicketResolved(SupportTicket $ticket): void
    {
        if ($ticket->customer !== null) {
            $this->notifyCustomerEvent(
                $ticket->customer,
                'support_solved',
                SmsTemplateVariableBuilder::forTicket($ticket),
            );
        }
    }

    public function onPortalOtp(Customer $customer, string $code, int $minutes = 10): void
    {
        $this->sendToCustomer(
            $customer,
            NotificationEvent::PORTAL_OTP,
            SmsTemplateVariableBuilder::forOtp($code, $minutes),
        );
    }

    private function customerWantsSms(Customer $customer): bool
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];

        return ! array_key_exists('notify_sms', $meta)
            || filter_var($meta['notify_sms'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    private function notifyCustomerEvent(Customer $customer, string $templateKey, array $variables): void
    {
        if (! $this->templates->isEnabled($templateKey, (int) $customer->tenant_id)) {
            return;
        }

        $message = $this->templates->render($templateKey, $variables, (int) $customer->tenant_id);
        if ($message === '') {
            return;
        }

        $channels = config("notifications.events.{$templateKey}.channels", ['sms']);
        if (! is_array($channels)) {
            $channels = ['sms'];
        }

        foreach ($channels as $channel) {
            if ($channel === NotificationChannel::SMS && $this->customerWantsSms($customer)) {
                $this->sendToCustomer($customer, $templateKey, $variables);
            }

            if ($channel === NotificationChannel::WHATSAPP) {
                $this->sendWhatsAppToCustomer($customer, $templateKey, $message);
            }
        }
    }

    private function sendWhatsAppToCustomer(Customer $customer, string $templateKey, string $message): void
    {
        if (! (bool) config('notifications.whatsapp.enabled', false)) {
            return;
        }

        if (! filled($customer->phone)) {
            return;
        }

        try {
            $this->dispatcher->send(
                (int) $customer->tenant_id,
                (int) $customer->id,
                $templateKey,
                NotificationChannel::WHATSAPP,
                (string) $customer->phone,
                $message,
                ['subject' => 'WhatsApp — '.$customer->name],
            );
        } catch (\Throwable $e) {
            Log::warning('automated_whatsapp.failed', [
                'template' => $templateKey,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendToCustomer(Customer $customer, string $templateKey, array $variables): void
    {
        if (! (bool) config('notifications.sms.enabled', false)) {
            return;
        }

        if (! $this->templates->isEnabled($templateKey, (int) $customer->tenant_id)) {
            return;
        }

        if (! filled($customer->phone)) {
            return;
        }

        $message = $this->templates->render($templateKey, $variables, (int) $customer->tenant_id);
        if ($message === '') {
            return;
        }

        try {
            $this->dispatcher->send(
                (int) $customer->tenant_id,
                (int) $customer->id,
                $templateKey,
                NotificationChannel::SMS,
                (string) $customer->phone,
                $message,
                ['subject' => 'SMS — '.$customer->name],
            );
        } catch (\Throwable $e) {
            Log::warning('automated_sms.failed', [
                'template' => $templateKey,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
