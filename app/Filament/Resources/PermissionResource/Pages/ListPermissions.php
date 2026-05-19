<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Pages\PermissionMatrix;
use App\Filament\Resources\PermissionResource;
use App\Services\Rbac\RolePermissionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    public function getHeading(): string
    {
        return 'Permission catalog';
    }

    public function getSubheading(): ?string
    {
        return 'Edit labels & categories · add custom permissions · keys stay fixed for system permissions';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('permission_matrix')
                ->label('Permission matrix')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(PermissionMatrix::getUrl()),
            Actions\Action::make('sync_catalog')
                ->label('Sync from catalog')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $created = app(RolePermissionService::class)->syncCatalog();
                    Notification::make()
                        ->title('Catalog synced')
                        ->body($created > 0 ? "{$created} new permission(s)." : 'All catalog permissions present.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('New permission'),
        ];
    }
}
