<?php

namespace App\Support;

use App\Models\User;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\Request;

trait StaffMobileApiAccess
{
    protected function staffMobileUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $cap = StaffCapability::for($user);
        abort_unless(
            $cap->isTenantAdmin()
                || $cap->canBilling()
                || $cap->canPayments()
                || $cap->canCollect()
                || $cap->canReports(),
            403,
            'You do not have permission to access billing documents.',
        );

        return $user;
    }

    protected function assertStaffTenant(User $user, int $tenantId): void
    {
        if ($user->tenant_id !== null && (int) $user->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
