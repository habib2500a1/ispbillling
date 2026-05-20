<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Billing\CollectionPaymentClassifier;
use App\Services\Sms\SmsTemplateVariableBuilder;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use App\Support\PaymentType;
use Illuminate\Support\Facades\Cache;

/**
 * Telegram / ops alerts for admins (independent of customer SMS template toggles).
 */
final class OpsNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function onPaymentCompleted(Payment $payment): void
    {
        if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PAYMENT) {
            return;
        }

        $event = CollectionPaymentClassifier::notificationEvent($payment);

        if (! $this->opsEnabledFor($event)) {
            return;
        }

        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        if (! $this->acquireTelegramOpsSendSlot($payment)) {
            return;
        }

        $invoice = $payment->invoice;
        $recorder = $payment->recorded_by ? User::query()->find($payment->recorded_by) : null;
        $vars = array_merge(
            SmsTemplateVariableBuilder::forPayment($payment),
            CollectionPaymentClassifier::notificationVariables($payment),
            [
                'method' => $payment->methodLabel(),
                'collected_by' => $recorder?->name ?? 'System / Gateway',
                'phone' => $customer->phone ?? '—',
                'due' => $invoice !== null
                    ? number_format(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2)
                    : '—',
                'time' => ($payment->paid_at ?? now())->format('d M Y, h:i A'),
            ],
        );

        $this->dispatcher->notifyOps((int) $payment->tenant_id, $event, $vars, [
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * One Telegram ops message per payment (guards duplicate observer / double-submit).
     */
    private function acquireTelegramOpsSendSlot(Payment $payment): bool
    {
        $key = 'notify:telegram_ops:payment:'.$payment->tenant_id.':'.$payment->id;

        if (! Cache::add($key, 1, now()->addHours(12))) {
            return false;
        }

        return ! NotificationLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $payment->tenant_id)
            ->where('channel', NotificationChannel::TELEGRAM)
            ->where('event', NotificationEvent::PAYMENT_SUCCESS)
            ->where('status', 'sent')
            ->where('meta->payment_id', $payment->id)
            ->exists();
    }

    public function onClientCreated(Customer $customer): void
    {
        if (! $this->opsEnabledFor('client_created')) {
            return;
        }

        $this->notifyCustomerEvent($customer, 'client_created', 'New client created');
    }

    public function onClientStatusChanged(Customer $customer, string $from, string $to): void
    {
        $key = SmsTemplateVariableBuilder::statusEventKey($customer, $from, $to);
        if ($key === null || ! in_array($key, ['client_enable', 'client_disable'], true)) {
            return;
        }

        if (! $this->opsEnabledFor($key)) {
            return;
        }

        $label = $key === 'client_enable' ? 'Client enabled' : 'Client disabled';
        $this->notifyCustomerEvent($customer, $key, $label, [
            'status_from' => $from,
            'status_to' => $to,
        ]);
    }

    public function onSupportTicketCreated(SupportTicket $ticket): void
    {
        if (! $this->opsEnabledFor('support_token_created')) {
            return;
        }

        $this->notifyTicketEvent($ticket, 'support_token_created', 'New support ticket');
    }

    public function onSupportTicketResolved(SupportTicket $ticket): void
    {
        if (! $this->opsEnabledFor('support_solved')) {
            return;
        }

        $this->notifyTicketEvent($ticket, 'support_solved', 'Support ticket resolved');
    }

    /**
     * @param  array<string, string|int|float|null>  $extra
     */
    private function notifyCustomerEvent(Customer $customer, string $eventKey, string $title, array $extra = []): void
    {
        $vars = array_merge(SmsTemplateVariableBuilder::forCustomer($customer), [
            'name' => $customer->name,
            'title' => $title,
            'time' => now()->format('d M Y, h:i A'),
        ], $extra);

        $this->dispatcher->notifyOps((int) $customer->tenant_id, $eventKey, $vars);
    }

    private function notifyTicketEvent(SupportTicket $ticket, string $eventKey, string $title): void
    {
        $customer = $ticket->customer;
        $assignee = $ticket->assigned_to ? User::query()->find($ticket->assigned_to) : null;

        $vars = array_merge(
            $customer !== null ? SmsTemplateVariableBuilder::forCustomer($customer) : [],
            SmsTemplateVariableBuilder::forTicket($ticket),
            [
                'name' => $customer?->name ?? 'Walk-in',
                'title' => $title,
                'ticket_number' => $ticket->ticket_number ?? (string) $ticket->id,
                'assignee' => $assignee?->name ?? 'Unassigned',
                'time' => now()->format('d M Y, h:i A'),
            ],
        );

        $this->dispatcher->notifyOps((int) $ticket->tenant_id, $eventKey, $vars);
    }

    private function opsEnabledFor(string $event): bool
    {
        if (! (bool) config('notifications.telegram.enabled', false)) {
            return false;
        }

        if (! filled(config('notifications.telegram.ops_chat_id'))) {
            return false;
        }

        $configKey = $event === NotificationEvent::PAYMENT_ADVANCE ? 'payment_success' : $event;

        return (bool) config("notifications.events.{$configKey}.telegram_ops", false);
    }
}
