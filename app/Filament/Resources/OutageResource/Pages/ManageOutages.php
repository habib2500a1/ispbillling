<?php

namespace App\Filament\Resources\OutageResource\Pages;

use App\Filament\Resources\OutageResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOutages extends ManageRecords
{
    protected static string $resource = OutageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
