<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOlts extends ListRecords
{
    protected static string $resource = OltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
