<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\Network\GponIntelligenceService;
use App\Services\Network\NetflowAnalysisService;
use App\Services\Network\NetflowIngestService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NetworkIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_netflow_webhook_ingests_flows(): void
    {
        config(['netflow.webhook_secret' => 'test-secret', 'netflow.enabled' => true]);

        $response = $this->postJson('/api/webhooks/netflow-ingest', [
            'exporter_ip' => '10.0.0.1',
            'flows' => [
                ['src' => '192.168.1.10', 'dst' => '8.8.8.8', 'bytes' => 5000, 'packets' => 10, 'proto' => 'udp'],
            ],
        ], ['X-Netflow-Secret' => 'test-secret']);

        $response->assertOk()->assertJson(['inserted' => 1]);
        $this->assertDatabaseHas('netflow_flows', [
            'src_ip' => '192.168.1.10',
            'dst_ip' => '8.8.8.8',
            'bytes' => 5000,
        ]);
    }

    public function test_netflow_webhook_rejects_bad_secret(): void
    {
        config(['netflow.webhook_secret' => 'test-secret']);

        $this->postJson('/api/webhooks/netflow-ingest', [
            'flows' => [['src' => '1.1.1.1', 'dst' => '2.2.2.2']],
        ], ['X-Netflow-Secret' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_netflow_analysis_summarizes_flows(): void
    {
        app(NetflowIngestService::class)->ingestPayload([
            'exporter_ip' => '10.0.0.2',
            'flows' => [
                ['src' => '10.1.1.5', 'dst' => '1.1.1.1', 'bytes' => 1000, 'packets' => 5],
                ['src' => '10.1.1.5', 'dst' => '8.8.4.4', 'bytes' => 2000, 'packets' => 8],
            ],
        ], 1);

        $summary = app(NetflowAnalysisService::class)->summary(1, 24);

        $this->assertSame(2, $summary['flow_count']);
        $this->assertSame(3000, $summary['total_bytes']);
        $this->assertNotEmpty($summary['top_sources']);
    }

    public function test_gpon_syncs_onu_optical_from_meta(): void
    {
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-'.uniqid(),
            'status' => 'active',
            'olt_driver' => 'huawei_gpon',
        ]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU-'.uniqid(),
            'status' => 'assigned',
            'olt_id' => $olt->id,
            'meta' => ['rx_power' => -22.5, 'onu_status' => 'online'],
        ]);

        $synced = app(GponIntelligenceService::class)->syncOnuOpticalFromMeta($onu->fresh());

        $this->assertTrue($synced);
        $onu->refresh();
        $this->assertSame(-22.5, (float) $onu->rx_power_dbm);
        $this->assertSame('online', $onu->onu_oper_status);
    }

    public function test_network_intelligence_hub_loads_for_admin(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/network-intelligence-hub')
            ->assertOk()
            ->assertSee('Network intelligence', false);
    }

    public function test_process_netflow_inbox_command(): void
    {
        $dir = storage_path('app/netflow/inbox');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.'/test-'.uniqid().'.json';
        file_put_contents($file, json_encode([
            'exporter_ip' => '172.16.0.1',
            'flows' => [['src' => '10.0.0.5', 'dst' => '10.0.0.6', 'bytes' => 100]],
        ]));

        $this->artisan('isp:process-netflow-inbox')->assertSuccessful();

        $this->assertDatabaseHas('netflow_flows', ['src_ip' => '10.0.0.5']);
        $this->assertFileDoesNotExist($file);
    }
}
