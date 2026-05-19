<?php

namespace Tests\Feature;

use App\Services\Rbac\RolePermissionService;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\IspRoleTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_catalog_seeds_all_keys(): void
    {
        app(RolePermissionService::class)->syncCatalog();

        $this->assertGreaterThanOrEqual(80, Permission::count());

        foreach (IspPermissionCatalog::all() as $key) {
            $this->assertTrue(
                Permission::where('name', $key)->exists(),
                "Missing permission: {$key}",
            );
        }
    }

    public function test_role_templates_create_expected_roles(): void
    {
        $this->seed(\Database\Seeders\IspRolesSeeder::class);

        $this->assertTrue(Role::where('name', 'super-admin')->exists());
        $this->assertTrue(Role::where('name', 'noc-engineer')->exists());
        $this->assertTrue(Role::where('name', 'support-agent')->exists());
        $this->assertTrue(Role::where('name', 'isp-support')->exists());

        $support = Role::findByName('support-agent', 'web');
        $this->assertTrue($support->hasPermissionTo('support.view'));
        $this->assertFalse($support->hasPermissionTo('security.impersonate'));
    }

    public function test_super_admin_has_full_catalog(): void
    {
        $this->seed(\Database\Seeders\IspRolesSeeder::class);

        $role = Role::findByName('super-admin', 'web');
        $this->assertSame(Permission::count(), $role->permissions()->count());
    }

    public function test_clone_role_copies_permissions(): void
    {
        $this->seed(\Database\Seeders\IspRolesSeeder::class);

        $source = Role::findByName('cashier', 'web');
        $clone = app(RolePermissionService::class)->cloneRole($source, 'cashier-backup');

        $this->assertSame(
            $source->permissions()->pluck('name')->sort()->values()->all(),
            $clone->permissions()->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_isp_role_templates_define_sixteen_roles(): void
    {
        $this->assertCount(16, IspRoleTemplates::all());
    }

    public function test_permission_display_name_can_be_updated(): void
    {
        app(RolePermissionService::class)->syncCatalog();

        $permission = Permission::where('name', 'customers.view')->first();
        $permission->update(['display_name' => 'View subscribers']);

        $this->assertSame('View subscribers', IspPermissionCatalog::labelFor('customers.view'));
    }
}
