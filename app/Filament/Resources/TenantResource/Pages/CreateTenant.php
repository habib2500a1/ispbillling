<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\Tenant\TenantProvisioningService;
use App\Services\Tenant\TenantSubscriptionService;
use App\Support\TenantSubscriptionCatalog;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $subscription = is_array($settings['subscription'] ?? null) ? $settings['subscription'] : [];
        $settings['subscription'] = app(TenantSubscriptionService::class)
            ->normalizeSubscriptionInput($subscription + [
                'plan_key' => $subscription['plan_key'] ?? TenantSubscriptionCatalog::PLAN_STARTER_100,
            ]);
        $data['settings'] = $settings;

        return $data;
    }

    protected function afterCreate(): void
    {
        $stats = app(TenantProvisioningService::class)->provision((int) $this->record->getKey());

        Notification::make()
            ->title('Tenant provisioned')
            ->body(sprintf(
                'Automatic processes: %d new · SMS templates: %d added',
                $stats['automatic_processes']['created'],
                $stats['sms_templates'],
            ))
            ->success()
            ->send();
    }
}
