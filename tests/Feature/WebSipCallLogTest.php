<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebSipCallLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_websip_call(): void
    {
        Role::findOrCreate('isp-admin');

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-log-test'],
            ['name' => 'WebSIP Log ISP', 'is_active' => true],
        );

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('isp-admin');

        Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'C-WSIP',
            'name' => 'Test Client',
            'phone' => '01710008877',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        config(['call_center.websip_enabled' => true]);

        $this->actingAs($user)
            ->postJson('/admin/websip/call-log', [
                'phone' => '01710008877',
                'status' => 'answered',
                'duration_seconds' => 42,
                'external_id' => 'websip-test-001',
                'direction' => 'outbound',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('call_logs', [
            'tenant_id' => $tenant->id,
            'staff_user_id' => $user->id,
            'phone' => '01710008877',
            'status' => 'answered',
            'external_id' => 'websip-test-001',
        ]);
    }

    public function test_duplicate_external_id_does_not_duplicate_row(): void
    {
        Role::findOrCreate('isp-admin');

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'websip-dedup'],
            ['name' => 'Dedup ISP', 'is_active' => true],
        );

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('isp-admin');

        config(['call_center.websip_enabled' => true]);

        $payload = [
            'phone' => '01710001122',
            'status' => 'failed',
            'duration_seconds' => 0,
            'external_id' => 'websip-dedup-1',
        ];

        $this->actingAs($user)->postJson('/admin/websip/call-log', $payload)->assertOk();
        $this->actingAs($user)->postJson('/admin/websip/call-log', $payload)->assertOk();

        $this->assertSame(1, CallLog::query()->where('external_id', 'websip-dedup-1')->count());
    }
}
