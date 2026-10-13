<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FeatureModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalModuleRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_modules_open_their_own_pages(): void
    {
        $this->assertSame('call-desk', FeatureModuleRegistry::find('call-center')['route'] ?? null);
        $this->assertSame('noc-outage', FeatureModuleRegistry::find('outage-broadcast')['route'] ?? null);
        $this->assertSame('inventory-purchases', FeatureModuleRegistry::find('purchase-orders')['route'] ?? null);
        $this->assertSame('hr-hub', FeatureModuleRegistry::find('employees')['route'] ?? null);

        $role = Role::create(['name' => 'Super Admin']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        foreach ([
            ['/noc', 'NOC Overview'],
            ['/noc-outage', 'Outage broadcast'],
            ['/call-desk', 'Call Desk'],
            ['/ops-insights', 'Ops Insights'],
        ] as [$path, $heading]) {
            $this->get('http://localhost'.$path)
                ->assertOk()
                ->assertSee($heading);
        }
    }
}
