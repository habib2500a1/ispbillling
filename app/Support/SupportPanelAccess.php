<?php

namespace App\Support;

use App\Models\User;

/**
 * Ticket / support UI authorization without requiring Spatie permission rows
 * (avoids PermissionDoesNotExist when migrations ran but seeders did not).
 */
final class SupportPanelAccess
{
    public static function viewTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-support',
            'isp-engineer',
            'isp-manager',
        ]);
    }

    public static function manageTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-support',
            'isp-manager',
            'isp-engineer',
        ]);
    }

    public static function assignTickets(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]);
    }

    public static function manageKnowledge(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]);
    }

    public static function manageOutages(?User $user): bool
    {
        return self::manageKnowledge($user);
    }
}
