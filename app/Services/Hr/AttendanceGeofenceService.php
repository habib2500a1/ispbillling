<?php

namespace App\Services\Hr;

use App\Models\AttendanceOfficeLocation;
use Illuminate\Validation\ValidationException;

final class AttendanceGeofenceService
{
    /**
     * @return array{
     *     allowed: bool,
     *     verified: bool,
     *     distance_meters: ?int,
     *     message: string,
     *     ip_ok: bool,
     *     gps_ok: bool,
     * }
     */
    public function evaluate(
        AttendanceOfficeLocation $office,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracyMeters,
        ?string $clientIp,
        string $status,
        bool $geofenceOverride = false,
    ): array {
        if ($status !== 'present') {
            return [
                'allowed' => true,
                'verified' => false,
                'distance_meters' => null,
                'message' => 'GPS not required for this status.',
                'ip_ok' => true,
                'gps_ok' => true,
            ];
        }

        if ($geofenceOverride) {
            return [
                'allowed' => true,
                'verified' => false,
                'distance_meters' => $this->distanceIfPossible($office, $latitude, $longitude),
                'message' => 'Geofence overridden by HR.',
                'ip_ok' => true,
                'gps_ok' => true,
            ];
        }

        if (! (bool) config('attendance.require_gps_for_present', true)) {
            return [
                'allowed' => true,
                'verified' => true,
                'distance_meters' => $this->distanceIfPossible($office, $latitude, $longitude),
                'message' => 'GPS check disabled in settings.',
                'ip_ok' => true,
                'gps_ok' => true,
            ];
        }

        $ipOk = $this->isIpAllowed($office, $clientIp);
        if (! $ipOk) {
            return [
                'allowed' => false,
                'verified' => false,
                'distance_meters' => null,
                'message' => 'Your IP ('.($clientIp ?: 'unknown').') is not allowed for office «'.$office->name.'».',
                'ip_ok' => false,
                'gps_ok' => false,
            ];
        }

        if ($latitude === null || $longitude === null) {
            return [
                'allowed' => false,
                'verified' => false,
                'distance_meters' => null,
                'message' => 'Allow browser location access, then tap «Use my GPS» before saving.',
                'ip_ok' => true,
                'gps_ok' => false,
            ];
        }

        $maxAccuracy = config('attendance.max_accuracy_meters');
        if ($maxAccuracy !== null && $accuracyMeters !== null && $accuracyMeters > (int) $maxAccuracy) {
            return [
                'allowed' => false,
                'verified' => false,
                'distance_meters' => null,
                'message' => 'GPS accuracy is too low ('.$accuracyMeters.' m). Move outdoors or retry.',
                'ip_ok' => true,
                'gps_ok' => false,
            ];
        }

        $distance = self::distanceMeters(
            (float) $office->latitude,
            (float) $office->longitude,
            $latitude,
            $longitude,
        );

        $radius = (int) ($office->radius_meters ?: config('attendance.default_radius_meters', 10));
        $gpsOk = $distance <= $radius;

        if (! $gpsOk) {
            return [
                'allowed' => false,
                'verified' => false,
                'distance_meters' => $distance,
                'message' => 'You are '.$distance.' m from «'.$office->name.'» (max '.$radius.' m). Check-in only at office.',
                'ip_ok' => true,
                'gps_ok' => false,
            ];
        }

        return [
            'allowed' => true,
            'verified' => true,
            'distance_meters' => $distance,
            'message' => 'Within office zone ('.$distance.' m / '.$radius.' m).',
            'ip_ok' => true,
            'gps_ok' => true,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanMark(
        AttendanceOfficeLocation $office,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracyMeters,
        ?string $clientIp,
        string $status,
        bool $geofenceOverride = false,
    ): array {
        $result = $this->evaluate($office, $latitude, $longitude, $accuracyMeters, $clientIp, $status, $geofenceOverride);

        if (! $result['allowed']) {
            throw ValidationException::withMessages([
                'latitude' => $result['message'],
            ]);
        }

        return $result;
    }

    public function isIpAllowed(AttendanceOfficeLocation $office, ?string $clientIp): bool
    {
        if (! (bool) config('attendance.enforce_office_ip', true)) {
            return true;
        }

        $allowed = array_values(array_filter($office->allowed_ips ?? []));
        if ($allowed === []) {
            return true;
        }

        $ip = trim((string) $clientIp);
        if ($ip === '') {
            return false;
        }

        foreach ($allowed as $rule) {
            if ($this->ipMatchesRule($ip, (string) $rule)) {
                return true;
            }
        }

        return false;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    private function distanceIfPossible(AttendanceOfficeLocation $office, ?float $lat, ?float $lng): ?int
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return self::distanceMeters((float) $office->latitude, (float) $office->longitude, $lat, $lng);
    }

    private function ipMatchesRule(string $ip, string $rule): bool
    {
        $rule = trim($rule);
        if ($rule === '') {
            return false;
        }

        if (! str_contains($rule, '/')) {
            return $ip === $rule;
        }

        if (! function_exists('inet_pton')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $rule, 2);
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 128) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = (int) floor($bits / 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
