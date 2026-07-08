<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\PlatformSuperAdmin;
use App\Support\PrimaryTenant;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return PlatformSuperAdmin::allows($user);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return PlatformSuperAdmin::allows($user);
    }

    public function create(User $user): bool
    {
        return PlatformSuperAdmin::allows($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return PlatformSuperAdmin::allows($user);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return PlatformSuperAdmin::allows($user) && ! PrimaryTenant::isPrimary($tenant->getKey());
    }
}
