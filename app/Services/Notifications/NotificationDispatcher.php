<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Services\Reseller\ResellerIntegrationSettings;
use App\Services\Reseller\ResellerScopedConfig;
use App\Services\Notifications\Channels\EmailNotificationChannel;
use App\Services\Notifications\Channels\NotificationChannelInterface;
use App\Services\Notifications\Channels\SmsNotificationChannel;
use App\Services\Notifications\Channels\TelegramNotificationChannel;
use App\Services\Notifications\Channels\WhatsAppNotificationChannel;
use App\Services\Sms\SmsTemplateService;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Log;

final class NotificationDispatcher
{
    /** @var array<string, NotificationChannelInterface> */
    private array $channels;

    public function __construct()
    {
        $this->channels = [
            NotificationChannel::EMAIL => new EmailNotificationChannel,
            NotificationChannel::SMS => new SmsNotificationChannel,
            NotificationChannel::WHATSAPP => new WhatsAppNotificationChannel,
            NotificationChannel::TELEGRAM => new TelegramNotificationChannel,
        ];
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     * @param  array<string, mixed>  $context
     */
    public function notifyCustomer(Customer $customer, string $event, array $variables = [], array $context = []): void
    {
        if (! $this->eventEnabled($event)) {
            return;
        }

        $message = MessageTemplateRenderer::render($event, array_merge(
            ['name' => $customer->name],
            $variables,
        ));

        if ($message === '') {
            return;
        }

        foreach ($this->channelsForEvent($event) as $channelName) {
            if ($channelName === NotificationChannel::SMS && ! app(SmsTemplateService::class)->isEnabled($event, (int) $customer->tenant_id)) {
                $this->logSkipped($customer, $event, $channelName, 'SMS template disabled');

                continue;
            }

            $recipient = $this->recipientForCustomer($customer, $channelName);
            if ($recipient === null) {
                $this->logSkipped($customer, $event, $channelName, 'No recipient');

                continue;
            }

            $this->send($customer->tenant_id, $customer->id, $event, $channelName, $recipient, $message, $context);
        }

        // Dedicated ops handlers (e.g. PaymentNotificationService) send full Telegram templates.
        if ($this->telegramOpsEnabled($event) && ! $this->hasDedicatedOpsHandler($event)) {
            $this->notifyOps((int) $customer->tenant_id, $event, array_merge(
                ['name' => $customer->name],
                $variables,
            ));
        }
    }

    private function hasDedicatedOpsHandler(string $event): bool
    {
        return in_array($event, [
            NotificationEvent::PAYMENT_SUCCESS,
            NotificationEvent::PAYMENT_ADVANCE,
        ], true);
    }

    /**
     * Admin Telegram alert — not gated by customer SMS template toggles.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyOps(int $tenantId, string $event, array $variables = [], array $context = []): void
    {
        if (! $this->telegramOpsEnabled($event)) {
            return;
        }

        $variables = array_merge([
            'count' => 0,
            'customer_list' => '—',
            'message' => '',
        ], $variables);

        $message = MessageTemplateRenderer::render($event.'_ops', $variables);
        if ($message === '') {
            return;
        }

        $chatId = (string) config('notifications.telegram.ops_chat_id', '');
        if ($chatId === '') {
            return;
        }

        $this->send($tenantId, null, $event, NotificationChannel::TELEGRAM, $chatId, $message, $context);
    }

    /**
     * @return int Number of notifications attempted
     */
    /**
     * @return int Number of messages attempted
     */
    public function broadcastCustom(
        int $tenantId,
        string $message,
        string $target = 'active',
        string $channel = NotificationChannel::SMS,
    ): int {
        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        $query = match ($target) {
            'due' => $query->where('status', 'active')->whereHas('invoices', fn ($q) => $q
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')),
            'suspended' => $query->where('status', 'suspended'),
            'all' => $query,
            default => $query->where('status', 'active'),
        };

        $event = 'promotional';
        $count = 0;

        foreach ($query->cursor() as $customer) {
            $recipient = $this->recipientForCustomer($customer, $channel);
            if ($recipient === null) {
                continue;
            }
            $this->send($tenantId, $customer->id, $event, $channel, $recipient, $message, []);
            $count++;
        }

        return $count;
    }

    public function broadcastOutage(int $tenantId, string $message, ?array $customerIds = null): int
    {
        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        if ($customerIds !== null && $customerIds !== []) {
            $query->whereIn('id', $customerIds);
        }

        $count = 0;
        $customerLines = [];
        foreach ($query->cursor() as $customer) {
            $this->notifyCustomer($customer, NotificationEvent::OUTAGE, [
                'message' => $message,
            ], ['subject' => 'Service notice']);
            $customerLines[] = sprintf(
                '%s | Code: %s | User: %s | ID: %d',
                $customer->name,
                $customer->customer_code ?? '—',
                $customer->pppLoginName(),
                $customer->id,
            );
            $count++;
        }

        $this->notifyOps($tenantId, NotificationEvent::OUTAGE, [
            'message' => $message,
            'count' => $count,
            'customer_list' => $this->formatCustomerListForOps($customerLines),
        ]);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(
        int $tenantId,
        ?int $customerId,
        string $event,
        string $channelName,
        string $recipient,
        string $message,
        array $context = [],
    ): void {
        $logMeta = array_filter([
            'payment_id' => isset($context['payment_id']) ? (int) $context['payment_id'] : null,
        ], fn ($v) => $v !== null);

        $log = NotificationLog::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'event' => $event,
            'channel' => $channelName,
            'recipient' => $recipient,
            'status' => 'pending',
            'message' => $message,
            'meta' => $logMeta !== [] ? $logMeta : null,
        ]);

        if ((bool) config('notifications.log_delivery_only', false)) {
            Log::info('notification.log_only', [
                'event' => $event,
                'channel' => $channelName,
                'recipient' => $recipient,
                'message' => $message,
            ]);
            $log->update(['status' => 'sent', 'sent_at' => now(), 'meta' => ['log_only' => true]]);

            return;
        }

        $channel = $this->channels[$channelName] ?? null;
        if ($channel === null) {
            $log->update([
                'status' => 'skipped',
                'error' => 'Channel disabled or unknown',
            ]);

            return;
        }

        try {
            $context['notification_log_id'] = $log->id;
            $context['tenant_id'] = $tenantId;

            $resellerId = $this->resellerIdForSms($customerId);
            if ($resellerId !== null && $channelName === NotificationChannel::SMS) {
                ResellerScopedConfig::using($resellerId, function () use ($channel, $recipient, $message, $context, $log): void {
                    $this->deliverChannel($channel, $recipient, $message, $context, $log);
                });

                return;
            }

            $this->deliverChannel($channel, $recipient, $message, $context, $log);
        } catch (\Throwable $e) {
            Log::warning('notification.send_failed', [
                'event' => $event,
                'channel' => $channelName,
                'error' => $e->getMessage(),
            ]);
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deliverChannel(
        NotificationChannelInterface $channel,
        string $recipient,
        string $message,
        array $context,
        NotificationLog $log,
    ): void {
        if (! $channel->isEnabled()) {
            $log->update([
                'status' => 'skipped',
                'error' => 'Channel disabled or unknown',
            ]);

            return;
        }

        $channel->send($recipient, $message, $context);
        $log->update(['status' => 'sent', 'sent_at' => now()]);
    }

    private function resellerIdForSms(?int $customerId): ?int
    {
        if ($customerId === null) {
            return null;
        }

        $customer = Customer::query()->withoutGlobalScopes()->find($customerId);
        $reseller = ResellerIntegrationSettings::resellerForCustomer($customer);

        return $reseller?->id;
    }

    private function eventEnabled(string $event): bool
    {
        return (bool) config("notifications.events.{$event}.enabled", true);
    }

    /**
     * @return list<string>
     */
    private function channelsForEvent(string $event): array
    {
        $channels = config("notifications.events.{$event}.channels", ['email']);
        if (! is_array($channels)) {
            return ['email'];
        }

        // Telegram ops alerts use notifyOps(); customer loop has no per-subscriber chat id.
        return array_values(array_filter(
            $channels,
            fn (mixed $channel): bool => is_string($channel) && $channel !== NotificationChannel::TELEGRAM,
        ));
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

    private function telegramOpsEnabled(string $event): bool
    {
        if (! (bool) config('notifications.telegram.enabled', false)) {
            return false;
        }

        if (! filled(config('notifications.telegram.ops_chat_id'))) {
            return false;
        }

        return (bool) config("notifications.events.{$event}.telegram_ops", false);
    }

    private function recipientForCustomer(Customer $customer, string $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::EMAIL => filter_var($customer->email, FILTER_VALIDATE_EMAIL) ? $customer->email : null,
            NotificationChannel::SMS => filled($customer->phone) ? $customer->phone : null,
            NotificationChannel::WHATSAPP => $this->whatsappNumber($customer),
            default => null,
        };
    }

    private function whatsappNumber(Customer $customer): ?string
    {
        $contact = $customer->contacts()->where('is_whatsapp', true)->first();
        if ($contact?->phone) {
            return $contact->phone;
        }

        return filled($customer->phone) ? $customer->phone : null;
    }

    private function logSkipped(Customer $customer, string $event, string $channel, string $reason): void
    {
        NotificationLog::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'event' => $event,
            'channel' => $channel,
            'recipient' => '',
            'status' => 'skipped',
            'error' => $reason,
        ]);
    }
}
