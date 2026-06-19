<?php

namespace App\Filament\Resources\SupportRootIncidentResource\Pages;

use App\Filament\Resources\SupportRootIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportRootIncidents extends ListRecords
{
    protected static string $resource = SupportRootIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
