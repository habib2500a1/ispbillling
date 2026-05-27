<?php

namespace App\Filament\Resources\AttendanceOfficeLocationResource\Pages;

use App\Filament\Resources\AttendanceOfficeLocationResource;
use App\Models\AttendanceOfficeLocation;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceOfficeLocation extends EditRecord
{
    protected static string $resource = AttendanceOfficeLocationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['is_default'])) {
            AttendanceOfficeLocation::query()
                ->where('tenant_id', $this->record->tenant_id)
                ->whereKeyNot($this->record->id)
                ->update(['is_default' => false]);
        }

        return $data;
    }
}
