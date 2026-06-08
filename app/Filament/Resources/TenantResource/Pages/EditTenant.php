<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\Tenant\TenantModuleSettingsService;
use App\Support\PrimaryTenant;
use App\Support\SafeCache;
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

        if (PrimaryTenant::isPrimary($this->record->getKey())) {
            $data['is_active'] = true;
            $data['slug'] = $this->record->slug;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! PrimaryTenant::isPrimary((int) $this->record->getKey())),
        ];
    }

    protected function afterSave(): void
    {
        app(TenantModuleSettingsService::class)->seedDefaults((int) $this->record->getKey());
        SafeCache::forget('tenant_modules:'.(int) $this->record->getKey());
        SafeCache::forget('tenant_org:snapshot:'.(int) $this->record->getKey());
    }
}
