<?php

namespace App\Filament\Resources\PopBoxResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\PopBoxResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPopBox extends EditRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = PopBoxResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $box = $this->getRecord();
        $this->configureInventoryFormShell(
            $box->name,
            $box->code.' · '.($box->area?->name ?? 'No area'),
            PopBoxResource::getUrl(),
            'POP boxes',
        );
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
