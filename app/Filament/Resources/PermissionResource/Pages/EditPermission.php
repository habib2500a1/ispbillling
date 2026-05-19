<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use App\Services\Staff\ActivityLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
        ];
    }

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->log(
            'rbac.permission.updated',
            "Permission updated: {$this->record->name}",
            $this->record,
            [
                'display_name' => $this->record->display_name,
                'category' => $this->record->category,
            ],
            'rbac',
        );
    }
}
