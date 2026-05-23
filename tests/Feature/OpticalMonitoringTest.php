<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Optical\OnuSignalCollectionService;
use App\Support\OnuSignalLevel;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_rx_level_classification(): void
    {
        $this->assertSame(OnuSignalLevel::EXCELLENT, OnuSignalLevel::classifyRx(-10.0, 'online'));
        $this->assertSame(OnuSignalLevel::GOOD, OnuSignalLevel::classifyRx(-18.0, 'online'));
        $this->assertSame(OnuSignalLevel::WARNING, OnuSignalLevel::classifyRx(-25.0, 'online'));
        $this->assertSame(OnuSignalLevel::CRITICAL, OnuSignalLevel::classifyRx(-28.0, 'online'));
        $this->assertSame(OnuSignalLevel::OFFLINE, OnuSignalLevel::classifyRx(-20.0, 'los'));
        $this->assertSame(OnuSignalLevel::HIGH, OnuSignalLevel::classifyRx(-0.5, 'online'));
    }

    public function test_collect_logs_signal_snapshot(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-OPT-1',
            'status' => 'active',
        ]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'serial_number' => 'ONU-OPT-1',
            'rx_power_dbm' => -18.5,
            'tx_power_dbm' => 2.1,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
        ]);

        $result = app(OnuSignalCollectionService::class)->collectForTenant(1);

        $this->assertGreaterThanOrEqual(1, $result['logged']);
        $this->assertDatabaseHas('onu_signal_logs', [
            'device_id' => $onu->id,
            'rx_level' => OnuSignalLevel::GOOD,
        ]);
        $this->assertDatabaseHas('onu_health_scores', [
            'device_id' => $onu->id,
        ]);
    }

    public function test_optical_webhook_ingests_reading(): void
    {
        config(['optical.webhook_secret' => 'opt-secret', 'optical.enabled' => true]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'SN-WEBHOOK-1',
            'status' => 'assigned',
        ]);

        $this->postJson('/api/webhooks/onu-optical-ingest', [
            'readings' => [
                ['serial' => 'SN-WEBHOOK-1', 'rx_dbm' => -14.2, 'tx_dbm' => 2.5, 'status' => 'online'],
            ],
        ], ['X-Optical-Secret' => 'opt-secret'])
            ->assertOk()
            ->assertJsonPath('processed', 1);

        $onu->refresh();
        $this->assertEqualsWithDelta(-14.2, (float) $onu->rx_power_dbm, 0.01);
    }

    public function test_webhook_auto_creates_onu_when_missing(): void
    {
        config(['optical.enabled' => true, 'optical.webhook_auto_create_onu' => true]);

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-WH',
            'status' => 'active',
        ]);

        $this->postJson('/api/webhooks/onu-optical-ingest', [
            'olt_id' => $olt->id,
            'create_missing' => true,
            'readings' => [
                ['serial' => 'NEW-ONU-99', 'rx_dbm' => -12.0, 'tx_dbm' => 2.0, 'status' => 'online'],
            ],
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('processed', 1);

        $this->assertDatabaseHas('devices', [
            'type' => 'onu',
            'serial_number' => 'NEW-ONU-99',
            'olt_id' => $olt->id,
        ]);
    }

    public function test_webhook_matches_ppp_login_to_customer_onu(): void
    {
        config([
            'optical.enabled' => true,
            'optical.auto_provision_customer_onu' => false,
        ]);

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-2',
            'status' => 'active',
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'C100',
            'mikrotik_secret_name' => 'user.ppp',
            'name' => 'Test',
            'phone' => '01719999999',
            'status' => 'active',
        ]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'customer_id' => $customer->id,
            'serial_number' => 'ONU-LINK-1',
            'status' => 'assigned',
        ]);

        $this->postJson('/api/webhooks/onu-optical-ingest', [
            'readings' => [
                ['ppp_login' => 'user.ppp', 'rx_dbm' => -18.0, 'status' => 'online'],
            ],
        ])->assertOk()->assertJsonPath('processed', 1);

        $onu->refresh();
        $this->assertEqualsWithDelta(-18.0, (float) $onu->rx_power_dbm, 0.01);
    }

    public function test_optical_noc_page_loads(): void
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        \Spatie\Permission\Models\Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/optical-noc')
            ->assertOk()
            ->assertSee('ONU optical');
    }

    public function test_optical_noc_livewire_renders_onu_without_health_score(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('isp-admin');
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-LW',
            'status' => 'active',
        ]);

        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'serial_number' => 'ONU-NO-HEALTH',
            'rx_power_dbm' => -20,
            'status' => 'assigned',
            'onu_oper_status' => 'online',
        ]);

        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Pages\OpticalMonitoringHub::class)
            ->call('setMonitorTab', 'onus')
            ->assertOk()
            ->assertSee('ONU-NO-HEALTH');
    }
}
