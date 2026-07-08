<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class StaffRoleGuard
{
    /** @var list<string> */
    public const PLATFORM_ONLY_ROLES = ['super-admin'];

    /** @var list<string> */
    public const TENANT_ADMIN_ROLES = ['admin', 'isp-admin'];

    /** @return array<string, string> */
    public static function assignableRoleOptions(?User $actor): array
    {
        $query = Role::query()->orderBy('name');

        if (! PlatformSuperAdmin::allows($actor)) {
            $query->whereNotIn('name', array_merge(self::PLATFORM_ONLY_ROLES, self::TENANT_ADMIN_ROLES));
        }

        return $query->pluck('name', 'name')->all();
    }

    /**
     * @param  list<string>  $roleNames
     */
    public static function assertCanAssignRoles(?User $actor, array $roleNames, ?Tenant $tenant = null): void
    {
        $roleNames = array_values(array_filter(array_map('strval', $roleNames)));

        if ($roleNames === []) {
            return;
        }

        if (PlatformSuperAdmin::allows($actor)) {
            return;
        }

        foreach ($roleNames as $role) {
            if (in_array($role, self::PLATFORM_ONLY_ROLES, true)) {
                throw ValidationException::withMessages([
                    'roles' => ['Only the platform super-admin may assign the Super Admin role.'],
                ]);
            }

            if (in_array($role, self::TENANT_ADMIN_ROLES, true)) {
                throw ValidationException::withMessages([
                    'roles' => ['Admin / ISP Admin roles are locked for rented tenants. Contact the platform owner.'],
                ]);
            }
        }

        $tenant ??= $actor?->tenant_id ? Tenant::query()->find((int) $actor->tenant_id) : null;

        if ($tenant !== null && ! TenantSaasControls::allowsStaffAdminRoles($tenant)) {
            foreach ($roleNames as $role) {
                if (in_array($role, self::TENANT_ADMIN_ROLES, true)) {
                    throw ValidationException::withMessages([
                        'roles' => ['This tenant is not allowed to grant Admin-level roles.'],
                    ]);
                }
            }
        }
    }

    public static function canAssignReseller(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (PlatformSuperAdmin::allows($user)) {
            return true;
        }

        $tenant = $user->tenant_id ? Tenant::query()->find((int) $user->tenant_id) : null;
        if ($tenant !== null && ! TenantSaasControls::allowsResellerCreation($tenant)) {
            return false;
        }

        if (\App\Support\Rbac\StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        return $user->can('customers.assign_reseller');
    }
}
