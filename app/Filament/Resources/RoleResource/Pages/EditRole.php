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
    protected array $permissionsBeforeSave = [];

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
                    $this->fillForm();
                })
                ->visible(fn (): bool => $this->record->name !== 'super-admin'),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! in_array($this->record->name, ['super-admin', 'isp-admin'], true)),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var Role $record */
        $record = $this->record;
        $this->permissionsBeforeSave = $record->permissions()->pluck('name')->sort()->values()->all();
    }

    protected function afterSave(): void
    {
        /** @var Role $record */
        $record = $this->record->fresh();
        $after = $record->permissions()->pluck('name')->sort()->values()->all();

        app(RolePermissionService::class)->logPermissionChange(
            $record,
            $this->permissionsBeforeSave,
            $after,
            'Permission matrix updated',
        );
    }
}
