<?php

namespace App\Filament\Resources\MikrotikServerResource\Pages;

use App\Filament\Resources\MikrotikServerResource;
use App\Models\MikrotikServer;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditMikrotikServer extends EditRecord
{
    protected static string $resource = MikrotikServerResource::class;

    protected static string $view = 'filament.resources.mikrotik-server-resource.pages.edit-mikrotik-server';

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-network-module isp-network-router-profile',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function hydrate(): void
    {
        if (! $this->record) {
            return;
        }

        if (blank($this->data['name'] ?? null) && blank($this->data['host'] ?? null)) {
            $this->fillForm();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['api_password'], $data['default_ppp_password']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tid = (int) $this->record->tenant_id;
        if (($data['name'] ?? '') !== $this->record->name) {
            if (MikrotikServer::query()->withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->where('name', $data['name'] ?? '')
                ->whereKeyNot($this->record->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'data.name' => 'This name is already used for your tenant.',
                ]);
            }
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'MikroTik server updated';
    }
}
