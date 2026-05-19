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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
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
