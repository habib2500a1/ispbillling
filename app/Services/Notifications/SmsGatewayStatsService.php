<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\SmsDeliveryReport;
use App\Support\TenantResolver;
final class SmsGatewayStatsService
{
    /**
     * @return array{
     *   balance: ?float,
     *   balance_label: string,
     *   balance_error: ?string,
     *   balance_fetched_at: ?string,
     *   today_sent: int,
     *   month_sent: int,
     *   month_failed: int,
     *   provider: string,
     *   provider_label: string,
     *   sms_enabled: bool,
     * }
     */
    public function snapshot(bool $refreshBalance = false): array
    {
        $tenantId = TenantResolver::currentTenantId();
        $balanceResult = app(SmsBalanceFetcher::class)->fetch($refreshBalance);

        return [
            'balance' => $balanceResult['balance'],
            'balance_label' => $balanceResult['label'],
            'balance_error' => $balanceResult['error'],
            'balance_fetched_at' => $balanceResult['fetched_at'],
            'today_sent' => $this->countSentToday($tenantId),
            'month_sent' => $this->countSentThisMonth($tenantId),
            'month_failed' => $this->countFailedThisMonth($tenantId),
            'provider' => (string) config('notifications.sms.provider', 'bulksmsbd'),
            'provider_label' => $this->providerLabel((string) config('notifications.sms.provider', 'bulksmsbd')),
            'sms_enabled' => (bool) config('notifications.sms.enabled', false),
        ];
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'khudebarta' => 'KhudeBarta (v2.0)',
            'bulksmsbd' => 'BulkSMSBD',
            'sslwireless' => 'SSL Wireless',
            'custom' => 'Custom HTTP',
            default => ucfirst($provider),
        };
    }

    private function countSentToday(?int $tenantId): int
    {
        return $this->smsLogQuery($tenantId)
            ->where('status', 'sent')
            ->whereDate('created_at', today())
            ->count();
    }

    private function countSentThisMonth(?int $tenantId): int
    {
        $start = now()->startOfMonth();

        return $this->smsLogQuery($tenantId)
            ->where('status', 'sent')
            ->where('created_at', '>=', $start)
            ->count();
    }

    private function countFailedThisMonth(?int $tenantId): int
    {
        $start = now()->startOfMonth();

        $logFailed = $this->smsLogQuery($tenantId)
            ->where('status', 'failed')
            ->where('created_at', '>=', $start)
            ->count();

        $dlrFailed = SmsDeliveryReport::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('delivery_status', 'failed')
            ->where('reported_at', '>=', $start)
            ->count();

        return max($logFailed, $dlrFailed);
    }

    private function smsLogQuery(?int $tenantId)
    {
        return NotificationLog::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('channel', 'sms');
    }
}
