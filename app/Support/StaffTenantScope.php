<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;

/**
 * Staff mobile/API tenant resolution — super-admin users often have null tenant_id.
 */
final class StaffTenantScope
{
    public static function tenantIdFor(User $user): int
    {
        if ($user->tenant_id !== null) {
            return (int) $user->tenant_id;
        }

        return TenantResolver::requiredTenantId();
    }

    public static function customerForStaff(User $user, int $customerId): Customer
    {
        $customer = Customer::withoutGlobalScopes()->whereKey($customerId)->firstOrFail();

        if ($user->tenant_id !== null && (int) $customer->tenant_id !== (int) $user->tenant_id) {
            abort(404, 'Customer not found.');
        }

        return $customer;
    }
}
