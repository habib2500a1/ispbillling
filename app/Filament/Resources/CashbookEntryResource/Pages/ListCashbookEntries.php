<?php

namespace App\Filament\Resources\CashbookEntryResource\Pages;

use App\Filament\Resources\CashbookEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashbookEntries extends ListRecords
{
    protected static string $resource = CashbookEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New cash entry')];
    }
}
