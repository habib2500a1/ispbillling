<?php

namespace App\Filament\Resources\PopBoxResource\Pages;

use App\Filament\Resources\PopBoxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPopBoxes extends ListRecords
{
    protected static string $resource = PopBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
