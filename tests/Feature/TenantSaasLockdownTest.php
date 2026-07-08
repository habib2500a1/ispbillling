<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\StaffRoleGuard;
use App\Support\TenantSaasControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantSaasLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_rented_tenant_cannot_create_resellers_by_default(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Rented ISP',
            'slug' => 'rented-isp',
            'is_active' => true,
        ]);

        $this->assertFalse(TenantSaasControls::allowsResellerCreation($tenant));
        $this->assertFalse(TenantSaasControls::allowsStaffAdminRoles($tenant));
    }

    public function test_tenant_staff_cannot_be_assigned_admin_roles_when_locked(): void
    {
        Role::findOrCreate('isp-admin');
        Role::findOrCreate('cashier');

        $tenant = Tenant::query()->create([
            'name' => 'Locked ISP',
            'slug' => 'locked-isp',
            'is_active' => true,
        ]);

        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('isp-admin');

        $this->expectException(ValidationException::class);
        StaffRoleGuard::assertCanAssignRoles($manager, ['cashier', 'isp-admin'], $tenant);
    }
}
