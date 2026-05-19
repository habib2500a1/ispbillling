<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Sms\SmsTemplateVariableBuilder;
use App\Support\NotificationEvent;

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
        if (! $this->opsEnabledFor('payment_success')) {
            return;
        }

        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $invoice = $payment->invoice;
        $recorder = $payment->recorded_by ? User::query()->find($payment->recorded_by) : null;

        $this->dispatcher->notifyOps((int) $payment->tenant_id, NotificationEvent::PAYMENT_SUCCESS, [
            'name' => $customer->name,
            'CustomerName' => $customer->name,
            'ClientID' => $customer->client_code ?? (string) $customer->id,
            'amount' => number_format((float) $payment->amount, 2),
            'PaidAmount' => number_format((float) $payment->amount, 2),
            'invoice_number' => $invoice?->invoice_number ?? '—',
            'receipt_number' => $payment->receipt_number ?? '—',
            'method' => $payment->methodLabel(),
            'collected_by' => $recorder?->name ?? 'System / Gateway',
            'phone' => $customer->phone ?? '—',
            'due' => $invoice !== null
                ? number_format(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2)
                : '—',
            'time' => ($payment->paid_at ?? now())->format('d M Y, h:i A'),
        ]);
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

        return (bool) config("notifications.events.{$event}.telegram_ops", false);
    }
}
