<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OltEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_olt_edit_page_loads(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-TEST-1',
            'management_ip' => '10.0.0.1',
            'olt_driver' => 'bdcom_epon',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get("/admin/olts/{$olt->id}/edit")
            ->assertOk()
            ->assertSee('OLT manage');
    }
}
