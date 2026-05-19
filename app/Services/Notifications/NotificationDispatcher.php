<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\NotificationLog;
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

        if ($this->telegramOpsEnabled($event)) {
            $this->notifyOps((int) $customer->tenant_id, $event, array_merge(
                ['name' => $customer->name],
                $variables,
            ));
        }
    }

    /**
     * Admin Telegram alert — not gated by customer SMS template toggles.
     *
     * @param  array<string, string|int|float|null>  $variables
     */
    public function notifyOps(int $tenantId, string $event, array $variables = []): void
    {
        if (! $this->telegramOpsEnabled($event)) {
            return;
        }

        $message = MessageTemplateRenderer::render($event.'_ops', $variables);
        if ($message === '') {
            return;
        }

        $chatId = (string) config('notifications.telegram.ops_chat_id', '');
        if ($chatId === '') {
            return;
        }

        $this->send($tenantId, null, $event, NotificationChannel::TELEGRAM, $chatId, $message, []);
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
        foreach ($query->cursor() as $customer) {
            $this->notifyCustomer($customer, NotificationEvent::OUTAGE, [
                'message' => $message,
            ], ['subject' => 'Service notice']);
            $count++;
        }

        $this->notifyOps($tenantId, NotificationEvent::OUTAGE, [
            'message' => $message,
            'count' => $count,
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
        $log = NotificationLog::query()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'event' => $event,
            'channel' => $channelName,
            'recipient' => $recipient,
            'status' => 'pending',
            'message' => $message,
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
        if ($channel === null || ! $channel->isEnabled()) {
            $log->update([
                'status' => 'skipped',
                'error' => 'Channel disabled or unknown',
            ]);

            return;
        }

        try {
            $context['notification_log_id'] = $log->id;
            $context['tenant_id'] = $tenantId;
            $channel->send($recipient, $message, $context);
            $log->update(['status' => 'sent', 'sent_at' => now()]);
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

        return is_array($channels) ? array_values(array_filter($channels, 'is_string')) : ['email'];
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
