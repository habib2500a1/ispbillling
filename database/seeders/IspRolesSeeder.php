<?php

namespace Database\Seeders;

use App\Services\Rbac\RolePermissionService;
use App\Support\Rbac\IspRoleTemplates;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IspRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->seedAllTemplates();

        $all = Permission::all();
        Role::findOrCreate('super-admin', 'web')->syncPermissions($all);
        Role::findOrCreate('isp-admin', 'web')->syncPermissions($all);

        $admin = Role::findByName('admin', 'web');
        if ($admin) {
            $admin->syncPermissions(IspRoleTemplates::permissionKeysFor('admin'));
        }
    }
}
