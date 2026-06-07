<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use App\Support\Rbac\StaffCapability;
use Illuminate\Database\Eloquent\Builder;
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

    protected function staffCustomerOrFail(User $user, int $customerId): Customer
    {
        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if ($customer === null) {
            abort(404);
        }

        $this->assertStaffTenant($user, (int) $customer->tenant_id);

        return $customer;
    }

    /**
     * @return Builder<Customer>
     */
    protected function staffCustomerQuery(User $user): Builder
    {
        $query = Customer::withoutGlobalScopes();

        if ($user->tenant_id !== null) {
            $query->where('tenant_id', (int) $user->tenant_id);
        }

        return $query;
    }
}
