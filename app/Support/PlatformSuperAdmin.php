<?php

namespace App\Support;

use App\Models\User;

/** Platform owner — only this role may create/edit SaaS tenants. */
final class PlatformSuperAdmin
{
    public static function allows(?User $user): bool
    {
        return $user !== null && $user->hasRole('super-admin');
    }
}
