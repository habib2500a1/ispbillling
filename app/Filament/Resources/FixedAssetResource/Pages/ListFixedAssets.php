<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Resources\FixedAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFixedAssets extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = FixedAssetResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Fixed assets';
        $this->inventoryListSubtitle = 'Capital equipment · depreciation notes · disposal tracking';
        $this->inventoryListCreateUrl = FixedAssetResource::getUrl('create');
        $this->inventoryListCreateLabel = 'New asset';
        $this->inventoryListStats = [
            ['label' => 'Fixed assets', 'value' => (string) ($m['total_fixed_assets'] ?? 0), 'tone' => 'sky'],
            ['label' => 'Total fleet', 'value' => number_format((int) ($m['total_assets'] ?? 0)), 'tone' => 'slate'],
            ['label' => 'Active CPE', 'value' => (string) ($m['active_assets'] ?? 0), 'tone' => 'emerald'],
            ['label' => 'Retired / faulty', 'value' => (string) ($m['damaged_assets'] ?? 0), 'tone' => 'rose'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Devices / CPE', 'url' => \App\Filament\Resources\DeviceResource::getUrl()],
            ['label' => 'Accounting', 'url' => \App\Filament\Pages\AccountingHub::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('New asset'),
        ];
    }
}
