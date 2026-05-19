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
        $this->sendToCustomer($customer, 'client_created', SmsTemplateVariableBuilder::forCustomer($customer));
    }

    public function onClientStatusChanged(Customer $customer, string $from, string $to): void
    {
        $key = SmsTemplateVariableBuilder::statusEventKey($customer, $from, $to);
        if ($key === null) {
            return;
        }

        $this->sendToCustomer($customer, $key, SmsTemplateVariableBuilder::forCustomer($customer));
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
            $this->sendToCustomer(
                $ticket->customer,
                'support_token_created',
                SmsTemplateVariableBuilder::forTicket($ticket),
            );
        }
    }

    public function onSupportTicketResolved(SupportTicket $ticket): void
    {
        if ($ticket->customer !== null) {
            $this->sendToCustomer(
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

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
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
