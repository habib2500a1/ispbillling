<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Resources\InventorySaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventorySales extends ListRecords
{
    protected static string $resource = InventorySaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New sale (POS)'),
        ];
    }
}
