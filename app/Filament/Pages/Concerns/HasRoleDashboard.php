<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Rbac\StaffCapability;

trait HasRoleDashboard
{
    protected static function staff(): StaffCapability
    {
        return StaffCapability::for(auth()->user());
    }

    /**
     * Role slugs alone no longer grant access — only tenant admins bypass.
     * Pages must check StaffCapability or Spatie permissions in canAccess().
     *
     * @param  list<string>  $roles
     */
    protected static function userHasAnyRole(array $roles): bool
    {
        return static::staff()->isTenantAdmin();
    }
}
