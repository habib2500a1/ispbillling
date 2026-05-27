<?php

namespace App\Filament\Resources\AttendanceOfficeLocationResource\Pages;

use App\Filament\Resources\AttendanceOfficeLocationResource;
use App\Models\AttendanceOfficeLocation;
use App\Support\TenantResolver;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceOfficeLocation extends CreateRecord
{
    protected static string $resource = AttendanceOfficeLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = TenantResolver::requiredTenantId();

        if (! empty($data['is_default'])) {
            AttendanceOfficeLocation::query()
                ->where('tenant_id', $data['tenant_id'])
                ->update(['is_default' => false]);
        }

        return $data;
    }
}
