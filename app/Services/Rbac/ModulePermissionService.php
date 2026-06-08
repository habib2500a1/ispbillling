<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Support\Rbac\IspModuleCatalog;
use Spatie\Permission\Models\Role;

final class ModulePermissionService
{
    public function __construct(
        private readonly RolePermissionService $roles,
    ) {}

    public function isModuleEnabled(Role $role, string $moduleKey): bool
    {
        $gate = IspModuleCatalog::gatePermission($moduleKey);
        if ($gate === null) {
            return false;
        }

        return $role->hasPermissionTo($gate);
    }

    /**
     * @return array<string, bool> moduleKey => enabled
     */
    public function moduleStatesForRole(Role $role): array
    {
        $states = [];
        foreach (IspModuleCatalog::modules() as $key => $meta) {
            $states[$key] = $this->isModuleEnabled($role, $key);
        }

        return $states;
    }

    public function setModuleEnabled(Role $role, string $moduleKey, bool $enabled): void
    {
        $keys = IspModuleCatalog::permissionKeys($moduleKey);
        if ($keys === []) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $valid = Permission::query()
            ->where('guard_name', $role->guard_name ?? 'web')
            ->whereIn('name', $keys)
            ->pluck('name')
            ->all();

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($enabled) {
            $role->givePermissionTo($valid);
        } else {
            foreach ($valid as $permission) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        $after = $role->fresh()->permissions()->pluck('name')->sort()->values()->all();
        $this->roles->logPermissionChange(
            $role,
            $before,
            $after,
            ($enabled ? 'Module ON' : 'Module OFF').': '.$moduleKey,
        );
    }

    public function toggleModule(Role $role, string $moduleKey): bool
    {
        $enabled = ! $this->isModuleEnabled($role, $moduleKey);
        $this->setModuleEnabled($role, $moduleKey, $enabled);

        return $enabled;
    }
}
