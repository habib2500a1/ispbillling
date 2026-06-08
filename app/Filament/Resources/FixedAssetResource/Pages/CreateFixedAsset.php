<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\FixedAssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFixedAsset extends CreateRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = FixedAssetResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(): void
    {
        parent::mount();
        $this->configureInventoryFormShell(
            'New fixed asset',
            'Office / field equipment · depreciation · lifecycle',
            FixedAssetResource::getUrl(),
            'Fixed assets',
        );
    }

    protected function getHeaderActions(): array
    {
        return [$this->inventoryHubAction()];
    }
}
