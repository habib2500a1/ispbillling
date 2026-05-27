<?php

namespace App\Filament\Resources\AttendanceRecordResource\Concerns;

use App\Models\AttendanceOfficeLocation;
use App\Services\Hr\AttendanceGeofenceService;
use App\Support\TenantResolver;

trait AppliesAttendanceGeofence
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyAttendanceGeofence(array $data): array
    {
        $data['tenant_id'] = $data['tenant_id'] ?? TenantResolver::requiredTenantId();
        $data['client_ip'] = request()->ip();

        $status = (string) ($data['status'] ?? 'present');
        $officeId = $data['attendance_office_location_id'] ?? null;

        if ($status !== 'present' || $officeId === null) {
            $data['location_verified'] = false;
            $data['geofence_override'] = (bool) ($data['geofence_override'] ?? false);

            return $data;
        }

        $office = AttendanceOfficeLocation::query()->find($officeId);
        if ($office === null) {
            return $data;
        }

        $override = (bool) ($data['geofence_override'] ?? false);
        if ($override && ! $this->canOverrideGeofence()) {
            $override = false;
            $data['geofence_override'] = false;
        }

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $accuracy = isset($data['accuracy_meters']) ? (int) $data['accuracy_meters'] : null;

        $result = app(AttendanceGeofenceService::class)->assertCanMark(
            $office,
            $lat,
            $lng,
            $accuracy,
            $data['client_ip'],
            $status,
            $override,
        );

        $data['distance_meters'] = $result['distance_meters'];
        $data['location_verified'] = $result['verified'];

        return $data;
    }

    protected function canOverrideGeofence(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('payroll.manage'));
    }
}
