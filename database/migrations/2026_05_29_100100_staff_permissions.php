<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'staff.view',
            'staff.manage',
            'branches.view',
            'branches.manage',
            'security.manage',
            'audit.view',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::query()->where('name', 'isp-admin')->where('guard_name', 'web')->first();
        if ($admin !== null) {
            $admin->givePermissionTo(
                Permission::query()->whereIn('name', [
                    'staff.view', 'staff.manage', 'branches.view', 'branches.manage', 'security.manage', 'audit.view',
                ])->get()
            );
        }
    }

    public function down(): void
    {
        // permissions retained on rollback
    }
};
