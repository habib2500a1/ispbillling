<?php

namespace App\Support;

use App\Models\User;
use App\Support\Rbac\StaffCapability;

/**
 * Portal / website "new connection" requests (SalesLead).
 */
final class SalesLeadPanelAccess
{
    public static function canView(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        if (SupportPanelAccess::viewTickets($user)) {
            return true;
        }

        return $user->canAny([
            'support.view',
            'customers.view',
            'customers.create',
        ]);
    }
}
