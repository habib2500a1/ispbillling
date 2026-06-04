<?php

namespace App\Filament\Resources\CallLogResource\Pages;

use App\Filament\Resources\CallLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCallLog extends CreateRecord
{
    protected static string $resource = CallLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['staff_user_id'] = auth()->id();
        $data['started_at'] = $data['started_at'] ?? now();

        return $data;
    }
}
