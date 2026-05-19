<?php

namespace App\Filament\Resources\InternalTaskResource\Pages;

use App\Filament\Resources\InternalTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInternalTask extends CreateRecord
{
    protected static string $resource = InternalTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
