<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'attendance_office_location_id',
        'work_date',
        'check_in',
        'check_out',
        'status',
        'notes',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_meters',
        'client_ip',
        'location_verified',
        'geofence_override',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'integer',
            'distance_meters' => 'integer',
            'location_verified' => 'boolean',
            'geofence_override' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceOfficeLocation::class, 'attendance_office_location_id');
    }

    public function distanceLabel(): string
    {
        if ($this->distance_meters === null) {
            return '—';
        }

        return number_format((int) $this->distance_meters).' m';
    }
}
