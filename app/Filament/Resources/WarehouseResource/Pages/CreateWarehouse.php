<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = WarehouseResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(): void
    {
        parent::mount();
        $this->configureInventoryFormShell(
            'New warehouse',
            'Storage location · default PO/sales routing · transfers',
            WarehouseResource::getUrl(),
            'Warehouses',
        );
    }

    protected function getHeaderActions(): array
    {
        return [$this->inventoryHubAction()];
    }
}
