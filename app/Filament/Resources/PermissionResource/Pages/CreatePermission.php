<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use App\Services\Staff\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->log(
            'rbac.permission.created',
            "Permission created: {$this->record->name}",
            $this->record,
            ['name' => $this->record->name],
            'rbac',
        );
    }
}
