<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouse extends EditRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = WarehouseResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $wh = $this->getRecord();
        $this->configureInventoryFormShell(
            $wh->name,
            $wh->code.' · '.($wh->is_active ? 'Active' : 'Inactive'),
            WarehouseResource::getUrl(),
            'Warehouses',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
