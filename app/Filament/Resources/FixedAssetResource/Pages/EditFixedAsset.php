<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\FixedAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFixedAsset extends EditRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = FixedAssetResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $asset = $this->getRecord();
        $this->configureInventoryFormShell(
            $asset->name,
            ($asset->asset_code ?: $asset->serial_number).' · '.ucfirst((string) $asset->status),
            FixedAssetResource::getUrl(),
            'Fixed assets',
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
