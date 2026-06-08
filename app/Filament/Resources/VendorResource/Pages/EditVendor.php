<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendor extends EditRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = VendorResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $vendor = $this->getRecord();
        $this->configureInventoryFormShell(
            $vendor->name,
            'Vendor profile · procurement history',
            VendorResource::getUrl(),
            'Vendors',
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
