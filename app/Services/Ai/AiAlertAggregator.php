<?php

namespace App\Services\Ai;

use App\Services\Dashboard\AiAnalyticsService;
use App\Services\Inventory\InventoryAssetIntelligenceService;
use App\Services\IspOs\FaultManagementService;
use App\Services\IspOs\OperationalInsightsService;
use App\Services\IspOs\RootCauseAnalysisService;
use App\Support\SafeCache;
use App\Support\TenantResolver;

/**
 * Merges cross-domain alerts for AI alert center (read-only).
 */
final class AiAlertAggregator
{
    /**
     * @return list<array<string, mixed>>
     */
    public function alerts(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'ai_copilot:alerts:'.$tenantId,
            now()->addSeconds(60),
            fn (): array => $this->build($tenantId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function build(int $tenantId): array
    {
        $alerts = [];
        $ai = app(AiAnalyticsService::class)->insights($tenantId);
        $faults = app(FaultManagementService::class)->payload($tenantId);
        $inventory = app(InventoryAssetIntelligenceService::class)->metrics($tenantId);

        foreach ($ai['recommendations'] ?? [] as $rec) {
            $alerts[] = [
                'severity' => ($rec['priority'] ?? 'medium') === 'high' ? 'high' : 'medium',
                'domain' => 'bi',
                'title' => (string) ($rec['text'] ?? 'Recommendation'),
                'hint' => 'AI analytics',
            ];
        }

        foreach (array_slice($faults['faults'] ?? [], 0, 5) as $fault) {
            $alerts[] = [
                'severity' => ($fault['severity'] ?? '') === 'critical' ? 'critical' : 'high',
                'domain' => 'noc',
                'title' => (string) ($fault['title'] ?? 'Network fault'),
                'hint' => (string) ($fault['message'] ?? ''),
                'url' => $fault['url'] ?? null,
            ];
        }

        foreach (app(OperationalInsightsService::class)->forTenant($tenantId) as $insight) {
            $alerts[] = [
                'severity' => ($insight['tone'] ?? '') === 'critical' ? 'critical' : 'medium',
                'domain' => 'noc',
                'title' => (string) ($insight['message'] ?? 'NOC insight'),
                'hint' => 'Operational insight',
            ];
        }

        foreach (app(RootCauseAnalysisService::class)->analyze($tenantId) as $rca) {
            $alerts[] = [
                'severity' => ($rca['tone'] ?? '') === 'critical' ? 'critical' : 'high',
                'domain' => 'noc',
                'title' => (string) ($rca['message'] ?? 'Root cause'),
                'hint' => 'RCA · '.round(((float) ($rca['confidence'] ?? 0)) * 100).'% confidence',
            ];
        }

        if ((int) ($inventory['low_stock_count'] ?? 0) > 0) {
            $alerts[] = [
                'severity' => 'medium',
                'domain' => 'inventory',
                'title' => ($inventory['low_stock_count'] ?? 0).' SKUs below reorder level',
                'hint' => 'Inventory',
                'url' => \App\Filament\Pages\InventoryHub::getUrl(),
            ];
        }

        if ((int) ($inventory['warranty_expiring'] ?? 0) > 0) {
            $alerts[] = [
                'severity' => 'medium',
                'domain' => 'inventory',
                'title' => ($inventory['warranty_expiring'] ?? 0).' warranties expiring in 30 days',
                'hint' => 'Asset warranty',
                'url' => \App\Filament\Pages\InventoryWarrantyManagement::getUrl(),
            ];
        }

        usort($alerts, fn (array $a, array $b): int => $this->severityRank($b['severity'] ?? '') <=> $this->severityRank($a['severity'] ?? ''));

        return array_slice($alerts, 0, 20);
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}
