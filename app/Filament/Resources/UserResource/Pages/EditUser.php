<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\UserCollectionDiscount;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->getRoleNames()->all();
        $cd = UserCollectionDiscount::prefs($this->record);
        $data['collection_discount_enabled'] = $cd['enabled'];
        $data['collection_discount_max_bdt'] = $cd['max_discount_bdt'];
        $data['collection_discount_max_percent'] = $cd['max_discount_percent_of_due'];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['roles'], $data['collection_discount_enabled'], $data['collection_discount_max_bdt'], $data['collection_discount_max_percent']);

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        $roles = $state['roles'] ?? [];
        if (is_array($roles)) {
            $this->record->syncRoles($roles);
        }

        $this->record->forceFill([
            'dashboard_preferences' => UserCollectionDiscount::mergeIntoDashboardPreferences($this->record, [
                'enabled' => (bool) ($state['collection_discount_enabled'] ?? false),
                'max_discount_bdt' => $state['collection_discount_max_bdt'] ?? null,
                'max_discount_percent_of_due' => $state['collection_discount_max_percent'] ?? null,
            ]),
        ])->saveQuietly();
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
