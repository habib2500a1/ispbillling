<?php

namespace Database\Seeders;

use App\Services\Rbac\RolePermissionService;
use Illuminate\Database\Seeder;

class IspPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->syncCatalog();
    }
}
