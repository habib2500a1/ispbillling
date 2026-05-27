<?php

namespace App\Filament\Resources\AttendanceRecordResource\Pages;

use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\AttendanceRecordResource\Concerns\AppliesAttendanceGeofence;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceRecord extends CreateRecord
{
    use AppliesAttendanceGeofence;

    protected static string $resource = AttendanceRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->applyAttendanceGeofence($data);
    }
}
