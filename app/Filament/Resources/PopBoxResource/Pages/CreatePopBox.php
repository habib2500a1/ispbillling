<?php

namespace App\Filament\Resources\PopBoxResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\PopBoxResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePopBox extends CreateRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = PopBoxResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(): void
    {
        parent::mount();
        $this->configureInventoryFormShell(
            'New POP / box',
            'GIS coordinates · capacity · fiber plant link',
            PopBoxResource::getUrl(),
            'POP boxes',
        );
    }
}
