<?php

namespace App\Support;

/**
 * Resolve legacy portal password for CLI/scheduler (avoids KEEP_CURRENT placeholder).
 */
final class LegacyPortalPassword
{
    public static function resolve(?string $override = null): string
    {
        foreach ([
            $override,
            config('legacy_portal.sync_password'),
            config('legacy_portal.password'),
            env('LEGACY_PORTAL_SYNC_PASSWORD'),
            env('LEGACY_PORTAL_PASSWORD'),
            env('ISP_DIGITAL_PASSWORD'),
        ] as $candidate) {
            $password = trim((string) $candidate);
            if ($password !== '' && $password !== 'KEEP_CURRENT') {
                return $password;
            }
        }

        return '';
    }
}
