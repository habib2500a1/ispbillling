<?php

namespace App\Filament\Resources\ResellerResource\Pages;

use App\Filament\Resources\ResellerResource;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\Resellers\ResellerPortalAccessService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateReseller extends CreateRecord
{
    protected static string $resource = ResellerResource::class;

    protected static string $view = 'filament.resources.reseller-resource.pages.create-reseller';

    protected ?string $plainPortalPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tid = auth()->user()?->tenant_id ?? (int) (Tenant::query()->value('id') ?? 1);
        $data['tenant_id'] = $tid;

        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        }

        if (blank($data['portal_login'] ?? null) && filled($data['code'] ?? null)) {
            $data['portal_login'] = $data['code'];
        }

        if (filled($data['portal_password'] ?? null)) {
            $this->plainPortalPassword = (string) $data['portal_password'];
            $data['portal_password'] = Hash::make($this->plainPortalPassword);
        }

        $opening = (float) ($data['opening_balance'] ?? 0);
        unset($data['opening_balance']);
        if ($opening > 0) {
            $data['wallet_balance'] = $opening;
        }

        if (filled($data['name'] ?? null) && Reseller::query()->withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('name', $data['name'])
            ->exists()) {
            throw ValidationException::withMessages([
                'data.name' => 'A reseller with this name already exists.',
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->plainPortalPassword !== null) {
            app(ResellerPortalAccessService::class)->storePlainPasswordIfKnown(
                $this->record,
                $this->plainPortalPassword,
            );
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Reseller created successfully';
    }
}
