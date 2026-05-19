<?php

namespace App\Filament\Resources\SubzoneResource\Pages;

use App\Filament\Resources\SubzoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubzone extends EditRecord
{
    protected static string $resource = SubzoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
