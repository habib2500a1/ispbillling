<?php

namespace App\Filament\Resources\SupportAssignmentRuleResource\Pages;

use App\Filament\Resources\SupportAssignmentRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportAssignmentRules extends ManageRecords
{
    protected static string $resource = SupportAssignmentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
