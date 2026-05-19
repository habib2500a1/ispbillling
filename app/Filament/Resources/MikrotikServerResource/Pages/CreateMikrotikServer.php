<?php

namespace App\Filament\Resources\MikrotikServerResource\Pages;

use App\Filament\Resources\MikrotikServerResource;
use App\Models\MikrotikServer;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateMikrotikServer extends CreateRecord
{
    protected static string $resource = MikrotikServerResource::class;

    protected static string $view = 'filament.resources.mikrotik-server-resource.pages.create-mikrotik-server';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tid = auth()->user()?->tenant_id ?? (int) (Tenant::query()->value('id') ?? 1);
        $data['tenant_id'] = $tid;

        if (MikrotikServer::query()->withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('name', $data['name'] ?? '')
            ->exists()) {
            throw ValidationException::withMessages([
                'data.name' => 'This name is already used for your tenant.',
            ]);
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'MikroTik server saved';
    }
}
