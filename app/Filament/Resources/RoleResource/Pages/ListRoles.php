<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Pages\PermissionMatrix;
use App\Filament\Resources\RoleResource;
use App\Services\Rbac\RolePermissionService;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\IspRoleTemplates;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    public function getHeading(): string
    {
        return 'Role management';
    }

    public function getSubheading(): ?string
    {
        return count(IspRoleTemplates::all()).' built-in templates · '.count(IspPermissionCatalog::all()).' permissions in catalog';
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
                ->label('Sync permission catalog')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $created = app(RolePermissionService::class)->syncCatalog();
                    Notification::make()
                        ->title('Permission catalog synced')
                        ->body($created > 0 ? "{$created} new permission(s) added." : 'Catalog already up to date.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('seed_templates')
                ->label('Reset role templates')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Re-applies all 15 staff role templates from the system catalog. Custom roles are not deleted.')
                ->action(function (): void {
                    app(RolePermissionService::class)->seedAllTemplates();
                    Notification::make()
                        ->title('Role templates applied')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('New role'),
        ];
    }
}
