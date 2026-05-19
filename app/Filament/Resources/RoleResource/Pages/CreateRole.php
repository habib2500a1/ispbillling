<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\Rbac\RolePermissionService;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleTemplate = $data['role_template'] ?? null;
        unset($data['role_template']);

        return $data;
    }

    protected ?string $roleTemplate = null;

    protected function afterCreate(): void
    {
        if (filled($this->roleTemplate)) {
            app(RolePermissionService::class)->applyTemplate($this->record, $this->roleTemplate);
        }
    }
}
