<?php

return [
    /** Require GPS within office radius when status is "present". */
    'require_gps_for_present' => (bool) env('ATTENDANCE_REQUIRE_GPS', true),

    /** Default geofence radius when creating a new office location. */
    'default_radius_meters' => (int) env('ATTENDANCE_DEFAULT_RADIUS_M', 10),

    /** Max GPS accuracy (m) to accept a reading; null = no limit. */
    'max_accuracy_meters' => env('ATTENDANCE_MAX_ACCURACY_M') !== null
        ? (int) env('ATTENDANCE_MAX_ACCURACY_M')
        : 100,

    /** When true, office allowed_ips (if set) must match client IP. */
    'enforce_office_ip' => (bool) env('ATTENDANCE_ENFORCE_OFFICE_IP', true),
];
