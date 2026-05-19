<?php

namespace App\Filament\Pages\Concerns;

trait HasRoleDashboard
{
    /**
     * @param  list<string>  $roles
     */
    protected static function userHasAnyRole(array $roles): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'isp-admin', 'admin'])) {
            return true;
        }

        return $user->hasAnyRole($roles);
    }
}
