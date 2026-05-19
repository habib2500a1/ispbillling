<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\PaymentLink;
use App\Support\TenantResolver;

final class DunningReportService
{
    /**
     * @return list<array{key: string, label: string, offset_days: int, eligible: int, sent_24h: int, channels: list<string>}>
     */
    public function stageRows(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $stages = config('billing.dunning.stages', []);
        $rows = [];

        foreach ($stages as $stage) {
            $key = (string) ($stage['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $offset = (int) ($stage['offset_days'] ?? 0);
            $targetDate = now()->startOfDay()->addDays($offset)->toDateString();

            $eligible = Invoice::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'partial'])
                ->whereRaw('(total - amount_paid) > 0')
                ->whereDate('due_date', $targetDate)
                ->count();

            $sent24h = NotificationLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('event', $key)
                ->where('created_at', '>=', now()->subDay())
                ->where('status', 'sent')
                ->count();

            $rawChannels = config("notifications.events.{$key}.channels", config('notifications.events.invoice_due.channels', ['sms']));
            $channels = is_array($rawChannels)
                ? array_values(array_filter($rawChannels, fn ($c): bool => is_string($c)))
                : ['sms'];

            $source = 'dunning:'.$key;
            $since = now()->subDay();
            $linkFilter = fn () => PaymentLink::query()
                ->where('tenant_id', $tenantId)
                ->where('source_event', $source)
                ->where('created_at', '>=', $since);

            $rows[] = [
                'key' => $key,
                'label' => (string) ($stage['label'] ?? $key),
                'offset_days' => $offset,
                'eligible' => $eligible,
                'sent_24h' => $sent24h,
                'channels' => $channels,
                'links_24h' => $linkFilter()->count(),
                'clicks_24h' => $linkFilter()->where('access_count', '>', 0)->count(),
                'converted_24h' => $linkFilter()->whereNotNull('converted_payment_id')->count(),
            ];
        }

        return $rows;
    }

    /**
     * @return array{enabled: bool, payment_links: bool, total_eligible: int, total_sent_24h: int, stages: list<array<string, mixed>>}
     */
    public function snapshot(?int $tenantId = null): array
    {
        $stages = $this->stageRows($tenantId);

        return [
            'enabled' => (bool) config('billing.dunning.enabled', true),
            'payment_links' => (bool) config('billing.dunning.include_payment_link', true),
            'total_eligible' => array_sum(array_column($stages, 'eligible')),
            'total_sent_24h' => array_sum(array_column($stages, 'sent_24h')),
            'stages' => $stages,
        ];
    }
}
