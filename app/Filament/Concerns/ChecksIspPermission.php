<?php

namespace App\Filament\Concerns;

trait ChecksIspPermission
{
    public static function canViewAny(): bool
    {
        return static::checkPermission(static::permissionPrefix().'.view');
    }

    public static function canCreate(): bool
    {
        return static::checkPermission(static::permissionPrefix().'.manage')
            || static::checkPermission(static::permissionPrefix().'.create');
    }

    public static function canEdit($record): bool
    {
        return static::checkPermission(static::permissionPrefix().'.manage')
            || static::checkPermission(static::permissionPrefix().'.update');
    }

    public static function canDelete($record): bool
    {
        return static::checkPermission(static::permissionPrefix().'.manage')
            || static::checkPermission(static::permissionPrefix().'.delete');
    }

    protected static function permissionPrefix(): string
    {
        return 'billing';
    }

    protected static function checkPermission(string $permission): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        if (\App\Support\Rbac\StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        return $user->can($permission);
    }
}
