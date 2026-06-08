<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\InventorySaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventorySales extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = InventorySaleResource::class;

    protected static string $view = 'filament.resources.inventory-sale-resource.pages.list-inventory-sales';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inventory_hub')
                ->label('Inventory center')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(InventoryHub::getUrl()),
            Actions\CreateAction::make()->label('New sale (POS)')->icon('heroicon-m-qr-code'),
        ];
    }
}
