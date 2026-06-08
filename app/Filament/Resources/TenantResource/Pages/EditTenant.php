<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $subscription = is_array($settings['subscription'] ?? null) ? $settings['subscription'] : [];
        $settings['subscription'] = app(\App\Services\Tenant\TenantSubscriptionService::class)
            ->normalizeSubscriptionInput($subscription);
        $data['settings'] = $settings;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
