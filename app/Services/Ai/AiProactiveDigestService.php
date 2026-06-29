<?php

namespace App\Services\Ai;

use App\Services\Dashboard\AiAnalyticsService;
use App\Services\IspOs\IspOsIntelligenceService;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Log;

final class AiProactiveDigestService
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiAlertAggregator $alerts,
    ) {}

    public function sendForTenant(int $tenantId): bool
    {
        if (! $this->settings->proactiveDigestEnabled($tenantId)) {
            return false;
        }

        $digest = $this->buildDigest($tenantId);
        if ($digest === '') {
            return false;
        }

        try {
            $this->sendTelegramDigest($tenantId, $digest);
            \Illuminate\Support\Facades\Log::info('ai.proactive_digest', ['tenant_id' => $tenantId]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('ai.proactive_digest.failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function buildDigest(int $tenantId): string
    {
        $isp = app(IspOsIntelligenceService::class)->payload($tenantId);
        $ai = app(AiAnalyticsService::class)->insights($tenantId);
        $alertCount = count($this->alerts->alerts($tenantId));

        $lines = [
            '📊 AI Operations Digest — '.now()->format('d M Y'),
            'Collection today: '.number_format((float) ($isp['collected_today'] ?? 0), 0).' BDT',
            'Open tickets: '.(int) ($isp['open_tickets'] ?? 0),
            'Customers offline: '.(int) ($isp['customers_offline'] ?? 0),
            'Churn risk: '.(int) ($ai['churn_risk_customers'] ?? 0).' subscriber(s)',
            'Overdue invoices: '.(int) ($ai['payment_risk_invoices'] ?? 0),
            'Active AI alerts: '.$alertCount,
        ];

        foreach (array_slice($ai['recommendations'] ?? [], 0, 3) as $rec) {
            $lines[] = '• '.(string) ($rec['text'] ?? '');
        }

        return implode("\n", $lines);
    }

    public function sendAllTenants(): int
    {
        $sent = 0;
        $tenants = \App\Models\Tenant::query()->where('is_active', true)->pluck('id');
        foreach ($tenants as $tenantId) {
            if ($this->sendForTenant((int) $tenantId)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendTelegramDigest(int $tenantId, string $digest): void
    {
        if (! config('notifications.telegram.enabled', false)) {
            return;
        }

        $token = (string) config('notifications.telegram.bot_token', '');
        $chatId = (string) config('notifications.telegram.ops_chat_id', '');
        if ($token === '' || $chatId === '') {
            return;
        }

        \Illuminate\Support\Facades\Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $digest,
        ]);
    }
}
