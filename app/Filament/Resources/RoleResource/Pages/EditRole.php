<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\Rbac\RolePermissionService;
use App\Support\Rbac\IspRoleTemplates;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    protected array $permissionKeys = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('apply_template')
                ->label('Apply template')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('template')
                        ->label('Role template')
                        ->options(IspRoleTemplates::options())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    app(RolePermissionService::class)->applyTemplate(
                        $this->record,
                        $data['template'],
                    );
                    $this->record->refresh();
                    $this->data['permission_keys'] = $this->record->permissions()->pluck('name')->all();
                })
                ->visible(fn (): bool => $this->record->name !== 'super-admin'),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! in_array($this->record->name, ['super-admin', 'isp-admin'], true)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $record */
        $record = $this->record;
        $data['permission_keys'] = $record->permissions()->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionKeys = array_values($data['permission_keys'] ?? []);
        unset($data['permission_keys']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(RolePermissionService::class)->syncRolePermissions(
            $this->record->fresh(),
            $this->permissionKeys,
            'Permission matrix updated',
        );
    }
}
