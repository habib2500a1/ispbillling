<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
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
        $type = $payment->payment_type ?? PaymentType::PAYMENT;
        if (! in_array($type, [PaymentType::PAYMENT, PaymentType::PREPAY], true)) {
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

    public function onNetworkAccessChanged(Customer $customer, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if ($to === 'suspended') {
            if (! $this->opsEnabledFor('client_disable')) {
                return;
            }
            $this->notifyCustomerEvent($customer, 'client_disable', 'Line suspended (auto/billing)', [
                'network_from' => $from,
                'network_to' => $to,
            ]);

            return;
        }

        if ($from === 'suspended' && $to === 'active') {
            if (! $this->opsEnabledFor('client_enable')) {
                return;
            }
            $this->notifyCustomerEvent($customer, 'client_enable', 'Line reconnected', [
                'network_from' => $from,
                'network_to' => $to,
            ]);
        }
    }

    public function onInvoiceCreated(Invoice $invoice): void
    {
        if (! $this->opsEnabledFor(NotificationEvent::INVOICE_CREATED)) {
            return;
        }

        $batch = app(InvoiceOpsNotificationBatch::class);
        if ($batch->isActive() && $this->invoiceOpsDigestEnabled()) {
            $batch->record($invoice);

            return;
        }

        $this->sendIndividualInvoiceOps($invoice);
    }

    /**
     * @param  list<Invoice>  $invoices
     */
    public function onInvoiceCreatedBulkDigest(int $tenantId, array $invoices, string $runLabel): void
    {
        if (! $this->opsEnabledFor(NotificationEvent::INVOICE_CREATED)) {
            return;
        }

        if ($invoices === []) {
            return;
        }

        $min = max(2, (int) config('notifications.events.invoice_created.telegram_ops_digest_min', 2));

        if (count($invoices) < $min) {
            foreach ($invoices as $invoice) {
                $this->sendIndividualInvoiceOps($invoice);
            }

            return;
        }

        if (! $this->acquireTelegramOpsSendSlotForBulkDigest($tenantId, count($invoices), $runLabel)) {
            return;
        }

        $lines = [];
        $total = 0.0;
        $month = now()->format('F');

        foreach ($invoices as $invoice) {
            $invoice = $invoice->fresh(['customer']) ?? $invoice;
            $customer = $invoice->customer;
            $amount = $invoice->balanceDue();
            $total += $amount;

            $issueDate = $invoice->issue_date ?? $invoice->period_start ?? now();
            if ($issueDate instanceof \Carbon\CarbonInterface) {
                $month = $issueDate->format('F');
            }

            $lines[] = sprintf(
                '%s (%s) — %s — %s BDT',
                $customer?->name ?? '—',
                $customer?->customer_code ?? '—',
                $invoice->invoice_number ?? '#'.$invoice->id,
                number_format($amount, 2),
            );
        }

        $this->dispatcher->notifyOps($tenantId, NotificationEvent::INVOICE_CREATED_BULK, [
            'run_label' => $runLabel,
            'count' => count($invoices),
            'total_amount' => number_format($total, 2),
            'Month' => $month,
            'customer_list' => $this->formatCustomerListForOps($lines),
            'time' => now()->format('d M Y, h:i A'),
        ], [
            'billing_digest' => true,
            'invoice_count' => count($invoices),
        ]);
    }

    private function sendIndividualInvoiceOps(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        if ($customer === null) {
            return;
        }

        if (! $this->acquireTelegramOpsSendSlotForInvoice($invoice)) {
            return;
        }

        $vars = array_merge(
            SmsTemplateVariableBuilder::forInvoice($invoice),
            [
                'name' => $customer->name,
                'time' => now()->format('d M Y, h:i A'),
            ],
        );

        $this->dispatcher->notifyOps((int) $invoice->tenant_id, NotificationEvent::INVOICE_CREATED, $vars, [
            'invoice_id' => $invoice->id,
        ]);
    }

    private function invoiceOpsDigestEnabled(): bool
    {
        return (bool) config('notifications.events.invoice_created.telegram_ops_digest', true);
    }

    /**
     * @param  list<string>  $lines
     */
    private function formatCustomerListForOps(array $lines): string
    {
        if ($lines === []) {
            return '—';
        }

        $max = 25;
        if (count($lines) <= $max) {
            return implode("\n", $lines);
        }

        $shown = array_slice($lines, 0, $max);

        return implode("\n", $shown)."\n… +".(count($lines) - $max).' more';
    }

    private function acquireTelegramOpsSendSlotForBulkDigest(int $tenantId, int $count, string $runLabel): bool
    {
        $digestKey = md5($runLabel.':'.$count);
        $key = 'notify:telegram_ops:billing_digest:'.$tenantId.':'.now()->toDateString().':'.$digestKey;

        if (! Cache::add($key, 1, now()->addHours(6))) {
            return false;
        }

        return ! NotificationLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('channel', NotificationChannel::TELEGRAM)
            ->where('event', NotificationEvent::INVOICE_CREATED_BULK)
            ->where('status', 'sent')
            ->where('meta->billing_digest', true)
            ->where('meta->invoice_count', $count)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();
    }

    /**
     * One Telegram ops message per invoice (guards duplicate generation / retries).
     */
    private function acquireTelegramOpsSendSlotForInvoice(Invoice $invoice): bool
    {
        $key = 'notify:telegram_ops:invoice:'.$invoice->tenant_id.':'.$invoice->id;

        if (! Cache::add($key, 1, now()->addHours(24))) {
            return false;
        }

        return ! NotificationLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('channel', NotificationChannel::TELEGRAM)
            ->where('event', NotificationEvent::INVOICE_CREATED)
            ->where('status', 'sent')
            ->where('meta->invoice_id', $invoice->id)
            ->exists();
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

        if ($event === NotificationEvent::INVOICE_CREATED_BULK) {
            return (bool) config('notifications.events.invoice_created.telegram_ops', false);
        }

        $configKey = $event === NotificationEvent::PAYMENT_ADVANCE ? 'payment_success' : $event;

        return (bool) config("notifications.events.{$configKey}.telegram_ops", false);
    }
}
