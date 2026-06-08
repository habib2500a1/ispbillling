<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\InventoryHub;
use App\Services\Inventory\InventoryAssetIntelligenceService;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Premium inventory list chrome (UI only — preserves Filament tables).
 */
trait UsesInventoryListLayout
{
    protected static string $inventoryListView = 'filament.inventory.list-shell';

    /** @var array<string, mixed> */
    public array $inventorySummary = [];

    public string $inventoryListTitle = '';

    public string $inventoryListSubtitle = '';

    /** @var list<array{label: string, value: string, tone: string}> */
    public array $inventoryListStats = [];

    public ?string $inventoryListCreateUrl = null;

    public ?string $inventoryListCreateLabel = null;

    /** @var list<array{label: string, url: string}> */
    public array $inventoryListLinks = [];

    public function mountInventoryListLayout(): void
    {
        $tenantId = TenantResolver::requiredTenantId();

        $this->inventorySummary = Cache::remember(
            'inventory_list_summary:'.$tenantId,
            120,
            fn (): array => app(InventoryAssetIntelligenceService::class)->metrics($tenantId),
        );
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-inventory-module',
        ];
    }

    protected function inventoryHubAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('inventory_hub')
            ->label('Asset center')
            ->icon('heroicon-o-cube')
            ->color('gray')
            ->url(InventoryHub::getUrl());
    }
}
