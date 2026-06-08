<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\VendorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendor extends CreateRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = VendorResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(): void
    {
        parent::mount();
        $this->configureInventoryFormShell(
            'New vendor',
            'Supplier contact · purchase orders · payable tracking',
            VendorResource::getUrl(),
            'Vendors',
        );
    }

    protected function getHeaderActions(): array
    {
        return [$this->inventoryHubAction()];
    }
}
