<?php

namespace Tests\Unit;

use App\Models\AttendanceOfficeLocation;
use App\Services\Hr\AttendanceGeofenceService;
use Tests\TestCase;

final class AttendanceGeofenceServiceTest extends TestCase
{
    public function test_distance_zero_at_same_point(): void
    {
        $d = AttendanceGeofenceService::distanceMeters(23.8103, 90.4125, 23.8103, 90.4125);

        $this->assertSame(0, $d);
    }

    public function test_rejects_outside_radius(): void
    {
        $office = new AttendanceOfficeLocation([
            'name' => 'HQ',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'radius_meters' => 10,
            'allowed_ips' => [],
        ]);

        $service = app(AttendanceGeofenceService::class);
        $result = $service->evaluate(
            $office,
            23.8105,
            90.4125,
            5,
            '127.0.0.1',
            'present',
        );

        $this->assertFalse($result['allowed']);
        $this->assertGreaterThan(10, $result['distance_meters'] ?? 0);
    }

    public function test_accepts_within_radius(): void
    {
        $office = new AttendanceOfficeLocation([
            'name' => 'HQ',
            'latitude' => 23.8103000,
            'longitude' => 90.4125000,
            'radius_meters' => 50,
            'allowed_ips' => [],
        ]);

        $service = app(AttendanceGeofenceService::class);
        $result = $service->evaluate(
            $office,
            23.8103100,
            90.4125100,
            8,
            '127.0.0.1',
            'present',
        );

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['verified']);
    }
}
