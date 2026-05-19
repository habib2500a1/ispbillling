<?php

namespace App\Filament\Resources\IntegrationSettingsAuditResource\Pages;

use App\Filament\Resources\IntegrationSettingsAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationSettingsAudits extends ListRecords
{
    protected static string $resource = IntegrationSettingsAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
