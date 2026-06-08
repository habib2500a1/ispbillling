<?php

namespace App\Filament\Resources\PopBoxResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Pages\FiberPlantMap;
use App\Filament\Resources\PopBoxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPopBoxes extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = PopBoxResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $this->inventoryListTitle = 'POP / boxes';
        $this->inventoryListSubtitle = 'Fiber plant nodes · GIS pins · capacity';
        $this->inventoryListCreateUrl = PopBoxResource::getUrl('create');
        $this->inventoryListCreateLabel = 'New POP';
        $this->inventoryListStats = [
            ['label' => 'Warehouses', 'value' => (string) ($this->inventorySummary['warehouse_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Stock value', 'value' => number_format((float) ($this->inventorySummary['stock_value'] ?? 0)).' BDT', 'tone' => 'orange'],
            ['label' => 'Devices', 'value' => (string) ($this->inventorySummary['total_devices'] ?? 0), 'tone' => 'teal'],
            ['label' => 'Fixed assets', 'value' => (string) ($this->inventorySummary['total_fixed_assets'] ?? 0), 'tone' => 'sky'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Fiber plant map', 'url' => FiberPlantMap::getUrl()],
            ['label' => 'Asset center', 'url' => \App\Filament\Pages\InventoryHub::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fiber_map')
                ->label('GIS map')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(FiberPlantMap::getUrl()),
            Actions\CreateAction::make()->label('New POP'),
        ];
    }
}
