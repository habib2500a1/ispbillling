<?php

namespace App\Filament\Resources\SubzoneResource\Pages;

use App\Filament\Resources\SubzoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubzones extends ListRecords
{
    protected static string $resource = SubzoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
