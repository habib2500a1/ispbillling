<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\StaffRoleGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['roles']);

        if (empty($data['tenant_id']) && auth()->user()?->tenant_id) {
            $data['tenant_id'] = auth()->user()->tenant_id;
        }

        return $data;
    }

    protected function beforeCreate(): void
    {
        $roles = $this->form->getState()['roles'] ?? [];
        if (is_array($roles)) {
            StaffRoleGuard::assertCanAssignRoles(auth()->user(), $roles);
        }
    }

    protected function afterCreate(): void
    {
        $roles = $this->form->getState()['roles'] ?? [];
        if (is_array($roles) && $roles !== []) {
            $this->record->syncRoles($roles);
        }
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
