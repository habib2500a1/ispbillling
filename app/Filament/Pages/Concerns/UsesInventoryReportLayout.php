<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\InventoryHub;
use App\Services\Inventory\InventoryAssetIntelligenceService;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Premium inventory report chrome (UI only).
 */
trait UsesInventoryReportLayout
{
    /** @var array<string, mixed> */
    public array $inventorySummary = [];

    /** @var list<array{label: string, value: string, tone: string}> */
    public array $reportStats = [];

    public function mountInventoryReportLayout(): void
    {
        $tenantId = TenantResolver::requiredTenantId();

        $this->inventorySummary = Cache::remember(
            'inventory_report_summary:'.$tenantId,
            120,
            fn (): array => app(InventoryAssetIntelligenceService::class)->metrics($tenantId),
        );

        $this->reportStats = method_exists($this, 'getReportStats')
            ? $this->getReportStats()
            : $this->defaultReportStats();
    }

    /**
     * @return list<array{label: string, value: string, tone: string}>
     */
    protected function defaultReportStats(): array
    {
        $m = $this->inventorySummary;

        return [
            ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'orange'],
            ['label' => 'SKUs', 'value' => (string) ($m['product_count'] ?? 0), 'tone' => 'teal'],
            ['label' => 'Low stock', 'value' => (string) ($m['low_stock_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Warehouses', 'value' => (string) ($m['warehouse_count'] ?? 0), 'tone' => 'sky'],
        ];
    }

    public function getWarrantyAlertCount(): int
    {
        return (int) ($this->inventorySummary['warranty_expiring'] ?? 0);
    }

    protected function inventoryHubUrl(): string
    {
        return InventoryHub::getUrl();
    }
}
