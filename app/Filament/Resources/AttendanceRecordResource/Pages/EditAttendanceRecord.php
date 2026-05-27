<?php

namespace App\Filament\Resources\AttendanceRecordResource\Pages;

use App\Filament\Resources\AttendanceRecordResource;
use App\Filament\Resources\AttendanceRecordResource\Concerns\AppliesAttendanceGeofence;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceRecord extends EditRecord
{
    use AppliesAttendanceGeofence;

    protected static string $resource = AttendanceRecordResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->applyAttendanceGeofence($data);
    }
}
