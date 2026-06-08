<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\PushNotificationLog;
use App\Models\SmsCampaign;
use App\Models\SmsTemplate;
use App\Models\VoiceSmsCampaign;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

/**
 * Read-only aggregator for Communication Hub UI (no send logic).
 */
final class CommsHubDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tenantId = TenantResolver::currentTenantId();
        $cacheKey = 'comms_hub:snapshot:'.($tenantId ?? 'global');

        return SafeCache::remember($cacheKey, 60, fn () => $this->buildSnapshot($tenantId));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(?int $tenantId): array
    {
        $smsStats = app(SmsGatewayStatsService::class)->snapshot();
        $todayStart = today();
        $sentToday = $this->logQuery($tenantId)->where('status', 'sent')->where('created_at', '>=', $todayStart);
        $failed24h = $this->logQuery($tenantId)->where('status', 'failed')->where('created_at', '>=', now()->subDay());

        $smsToday = (clone $sentToday)->where('channel', 'sms')->count();
        $whatsappToday = (clone $sentToday)->where('channel', 'whatsapp')->count();
        $emailToday = (clone $sentToday)->where('channel', 'email')->count();
        $failedCount = (clone $failed24h)->count();

        $pushToday = PushNotificationLog::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'sent')
            ->where('created_at', '>=', $todayStart)
            ->count();

        $monthSent = $this->logQuery($tenantId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $monthFailed = $this->logQuery($tenantId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $deliveryRate = $monthSent + $monthFailed > 0
            ? round(($monthSent / ($monthSent + $monthFailed)) * 100, 1)
            : 100.0;

        return [
            'kpis' => [
                'sms_today' => $smsToday,
                'whatsapp_today' => $whatsappToday,
                'email_today' => $emailToday,
                'push_today' => $pushToday,
                'failed_24h' => $failedCount,
                'scheduled' => $this->scheduledCount($tenantId),
                'active_campaigns' => $this->activeCampaignCount($tenantId),
                'delivery_rate' => $deliveryRate,
            ],
            'channels' => $this->channelStatus(),
            'billing_automation' => $this->billingAutomation(),
            'ticket_automation' => $this->ticketAutomation(),
            'recent_failures' => $this->recentFailures($tenantId, 8),
            'recent_logs' => $this->recentLogs($tenantId, 12),
            'campaigns' => $this->unifiedCampaigns($tenantId, 10),
            'scheduled' => $this->scheduledItems($tenantId),
            'analytics' => $this->analyticsSeries($tenantId, 7),
            'templates_summary' => $this->templatesSummary($tenantId),
            'smart_alerts' => $this->smartAlerts($tenantId),
            'inbox' => $this->opsInbox($tenantId, 15),
            'sms_gateway' => $smsStats,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $tenantId = TenantResolver::currentTenantId();
        $results = [];
        $likeOp = Customer::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        Customer::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('customer_code', 'like', "%{$q}%")
                    ->orWhere('radius_username', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get(['id', 'name', 'phone', 'customer_code'])
            ->each(function (Customer $c) use (&$results): void {
                $results[] = [
                    'type' => 'customer',
                    'id' => $c->id,
                    'label' => $c->name,
                    'meta' => $c->customer_code.' · '.$c->phone,
                    'url' => \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $c->id]),
                ];
            });

        $this->logQuery($tenantId)
            ->where(function ($qb) use ($q): void {
                $qb->where('recipient', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%")
                    ->orWhere('event', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'channel', 'recipient', 'event', 'status', 'created_at'])
            ->each(function (NotificationLog $log) use (&$results): void {
                $results[] = [
                    'type' => 'log',
                    'id' => $log->id,
                    'label' => strtoupper($log->channel).' · '.$log->event,
                    'meta' => $log->recipient.' · '.$log->status,
                    'url' => \App\Filament\Resources\NotificationLogResource::getUrl('index'),
                ];
            });

        SmsTemplate::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('key', 'like', "%{$q}%")
                    ->orWhere('event_key', 'like', "%{$q}%");
            })
            ->limit(6)
            ->get(['id', 'name', 'key', 'event_key'])
            ->each(function (SmsTemplate $t) use (&$results): void {
                $results[] = [
                    'type' => 'template',
                    'id' => $t->id,
                    'label' => $t->name,
                    'meta' => $t->key ?: $t->event_key,
                    'url' => \App\Filament\Resources\SmsTemplateResource::getUrl('edit', ['record' => $t->id]),
                ];
            });

        SmsCampaign::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where('name', $likeOp, "%{$q}%")
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'status', 'recipient_count'])
            ->each(function (SmsCampaign $c) use (&$results): void {
                $results[] = [
                    'type' => 'campaign',
                    'id' => $c->id,
                    'label' => $c->name,
                    'meta' => $c->status.' · '.$c->recipient_count.' recipients',
                    'url' => \App\Filament\Pages\BulkSmsCampaign::getUrl(),
                ];
            });

        return array_slice($results, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerTimeline(int $customerId, int $limit = 30): array
    {
        $tenantId = TenantResolver::currentTenantId();

        return $this->logQuery($tenantId)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
                'status' => $log->status,
                'message' => mb_substr((string) $log->message, 0, 160),
                'sent_at' => $log->sent_at?->toIso8601String() ?? $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function channelStatus(): array
    {
        $smsOk = (bool) config('notifications.sms.enabled')
            && filled(config('notifications.sms.api_key'))
            && (config('notifications.sms.provider') !== 'khudebarta' || filled(config('notifications.sms.secret_key')));

        return [
            ['key' => 'sms', 'label' => 'SMS', 'on' => $smsOk, 'icon' => 'sms'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'on' => (bool) config('notifications.whatsapp.enabled'), 'icon' => 'whatsapp'],
            ['key' => 'email', 'label' => 'Email', 'on' => (bool) config('notifications.email.enabled'), 'icon' => 'email'],
            ['key' => 'push', 'label' => 'Push', 'on' => class_exists(\App\Services\Mobile\PushNotificationService::class), 'icon' => 'push'],
            ['key' => 'telegram', 'label' => 'Telegram (ops)', 'on' => (bool) config('notifications.telegram.enabled'), 'icon' => 'telegram'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function billingAutomation(): array
    {
        $events = config('notifications.events', []);
        $stages = config('billing.dunning.stages', []);

        $items = [];
        foreach ($stages as $stage) {
            $eventKey = $stage['key'] ?? '';
            $items[] = [
                'label' => $stage['label'] ?? $eventKey,
                'event' => $eventKey,
                'offset_days' => $stage['offset_days'] ?? 0,
                'enabled' => (bool) ($events[$eventKey]['enabled'] ?? false),
            ];
        }

        return [
            'stages' => $items,
            'scheduler' => 'isp:send-invoice-due-reminders',
            'schedule' => 'Daily 09:00',
            'payment_alerts' => (bool) ($events['payment_success']['enabled'] ?? true),
            'fup_alerts' => (bool) ($events['fup_warning']['enabled'] ?? false),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ticketAutomation(): array
    {
        $events = config('notifications.events', []);

        return [
            ['label' => 'Ticket created', 'channels' => 'Email + SMS + Ops', 'enabled' => true],
            ['label' => 'Ticket assigned', 'channels' => 'Email', 'enabled' => true],
            ['label' => 'Public reply', 'channels' => 'Email + Push', 'enabled' => true],
            ['label' => 'Ticket resolved', 'channels' => 'Email + SMS', 'enabled' => (bool) ($events['support_solved']['enabled'] ?? true)],
            ['label' => 'SLA breach', 'channels' => 'Email managers', 'enabled' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFailures(?int $tenantId, int $limit): array
    {
        return $this->logQuery($tenantId)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->with('customer:id,name,customer_code')
            ->get()
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
                'recipient' => $log->recipient,
                'error' => mb_substr((string) ($log->error ?? 'Unknown'), 0, 80),
                'customer' => $log->customer?->name,
                'at' => $log->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentLogs(?int $tenantId, int $limit): array
    {
        return $this->logQuery($tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'channel', 'event', 'status', 'recipient', 'created_at'])
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
                'status' => $log->status,
                'recipient' => $log->recipient,
                'at' => $log->created_at?->format('M j, H:i'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unifiedCampaigns(?int $tenantId, int $limit): array
    {
        $sms = SmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SmsCampaign $c) => [
                'id' => 'sms-'.$c->id,
                'name' => $c->name,
                'type' => $this->campaignTypeLabel($c->target),
                'channel' => strtoupper($c->channel ?: 'SMS'),
                'targets' => (int) $c->recipient_count,
                'status' => $c->status,
                'at' => $c->sent_at?->format('M j') ?? $c->created_at?->format('M j'),
            ]);

        $voice = VoiceSmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (VoiceSmsCampaign $c) => [
                'id' => 'voice-'.$c->id,
                'name' => $c->name,
                'type' => 'Scheduled',
                'channel' => ($c->send_sms ? 'SMS' : '').($c->send_voice ? '+Voice' : ''),
                'targets' => (int) $c->targets_count,
                'status' => $c->status,
                'at' => $c->scheduled_at?->format('M j H:i') ?? '—',
            ]);

        return $sms->merge($voice)->sortByDesc('at')->take($limit)->values()->all();
    }

    private function campaignTypeLabel(?string $target): string
    {
        return match ($target) {
            'due' => 'Due Bill',
            'active' => 'Active subs',
            'suspended' => 'Suspended',
            'all' => 'All subscribers',
            default => 'Promotional',
        };
    }

    private function scheduledCount(?int $tenantId): int
    {
        return VoiceSmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['scheduled', 'pending', 'queued'])
            ->where('scheduled_at', '>', now())
            ->count();
    }

    private function activeCampaignCount(?int $tenantId): int
    {
        $sms = SmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['sending', 'processing', 'pending'])
            ->count();

        $voice = VoiceSmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['running', 'processing'])
            ->count();

        return $sms + $voice;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scheduledItems(?int $tenantId): array
    {
        return VoiceSmsCampaign::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get(['id', 'name', 'scheduled_at', 'status', 'targets_count'])
            ->map(fn (VoiceSmsCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'scheduled_at' => $c->scheduled_at?->format('M j, H:i'),
                'targets' => $c->targets_count,
                'status' => $c->status,
            ])
            ->all();
    }

    /**
     * @return array{labels: list<string>, sent: list<int>, failed: list<int>, by_channel: array<string, int>}
     */
    private function analyticsSeries(?int $tenantId, int $days): array
    {
        $labels = [];
        $sent = [];
        $failed = [];
        $byChannel = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $end = $date->copy()->endOfDay();
            $labels[] = $date->format('D');

            $sent[] = $this->logQuery($tenantId)
                ->where('status', 'sent')
                ->whereBetween('created_at', [$date, $end])
                ->count();

            $failed[] = $this->logQuery($tenantId)
                ->where('status', 'failed')
                ->whereBetween('created_at', [$date, $end])
                ->count();
        }

        $this->logQuery($tenantId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('channel, count(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->each(function ($count, $channel) use (&$byChannel): void {
                $byChannel[(string) $channel] = (int) $count;
            });

        return [
            'labels' => $labels,
            'sent' => $sent,
            'failed' => $failed,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * @return array{total: int, enabled: int, categories: list<array{label: string, count: int}>}
     */
    private function templatesSummary(?int $tenantId): array
    {
        $templates = SmsTemplate::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['event_key', 'is_enabled']);

        $categories = $templates->groupBy(function ($t) {
            $key = (string) ($t->event_key ?? 'custom');

            return match (true) {
                str_contains($key, 'invoice') || str_contains($key, 'due') || str_contains($key, 'payment') => 'Billing',
                str_contains($key, 'support') => 'Ticket',
                str_contains($key, 'outage') => 'Outage',
                str_contains($key, 'portal') || str_contains($key, 'otp') => 'Portal',
                str_contains($key, 'promo') => 'Promotional',
                default => 'General',
            };
        })->map(fn (Collection $group, string $label) => [
            'label' => $label,
            'count' => $group->count(),
        ])->values()->all();

        return [
            'total' => $templates->count(),
            'enabled' => $templates->where('is_enabled', true)->count(),
            'categories' => $categories,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function smartAlerts(?int $tenantId): array
    {
        $alerts = [];
        $failed24h = $this->logQuery($tenantId)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();

        if ($failed24h >= 10) {
            $alerts[] = [
                'severity' => 'high',
                'title' => 'High SMS failure rate',
                'detail' => "{$failed24h} failed messages in 24h",
                'url' => \App\Filament\Resources\NotificationLogResource::getUrl('failed'),
            ];
        }

        if (! (bool) config('notifications.sms.enabled')) {
            $alerts[] = [
                'severity' => 'medium',
                'title' => 'SMS channel disabled',
                'detail' => 'Enable SMS in notification settings',
                'url' => \App\Filament\Pages\ManageNotifications::getUrl(),
            ];
        }

        $balance = app(SmsGatewayStatsService::class)->snapshot()['balance'];
        if ($balance !== null && $balance < 100) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Low SMS balance',
                'detail' => 'Balance: '.number_format((float) $balance, 0),
                'url' => \App\Filament\Pages\SmsGatewaySetup::getUrl(),
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function opsInbox(?int $tenantId, int $limit): array
    {
        $items = collect();

        $this->logQuery($tenantId)
            ->whereIn('event', ['payment_success', 'invoice_due', 'invoice_overdue_3', 'invoice_overdue_7', 'outage', 'support_token_created', 'support_solved'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (NotificationLog $log) use ($items): void {
                $items->push([
                    'type' => $this->inboxType($log->event),
                    'title' => str_replace('_', ' ', ucfirst($log->event)),
                    'detail' => mb_substr((string) $log->message, 0, 100),
                    'channel' => $log->channel,
                    'status' => $log->status,
                    'at' => $log->created_at?->diffForHumans(),
                ]);
            });

        return $items->take($limit)->values()->all();
    }

    private function inboxType(string $event): string
    {
        return match (true) {
            str_contains($event, 'invoice') || str_contains($event, 'payment') => 'billing',
            str_contains($event, 'support') => 'ticket',
            str_contains($event, 'outage') => 'network',
            default => 'customer',
        };
    }

    private function logQuery(?int $tenantId)
    {
        return NotificationLog::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
    }
}
