<?php

namespace App\Filament\Resources\AttendanceOfficeLocationResource\Pages;

use App\Filament\Resources\AttendanceOfficeLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceOfficeLocations extends ListRecords
{
    protected static string $resource = AttendanceOfficeLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
