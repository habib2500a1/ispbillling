<?php

namespace App\Support;

use App\Models\User;
use App\Support\Rbac\StaffCapability;

/**
 * Who can see MFS / gateway admin screens in Filament.
 */
final class PaymentAdminAccess
{
    public static function canManageGateways(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        if ($user->hasAnyRole(['isp-manager', 'branch-manager'])) {
            return true;
        }

        return $user->can('system.settings');
    }

    public static function canViewPaymentOps(?User $user = null): bool
    {
        return StaffCapability::for($user)->canPayments()
            || self::canManageGateways($user);
    }
}
