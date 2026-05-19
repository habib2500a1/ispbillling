<?php

namespace Tests\Feature;

use App\Models\BandwidthAbuseAlert;
use App\Models\BandwidthSample;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\AbuseDetectionService;
use App\Services\Bandwidth\BandwidthCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandwidthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'Net 50',
            'type' => 'residential',
            'download_mbps' => 50,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Bandwidth User',
            'phone' => '01731112233',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }

    public function test_wan_per_second_bandwidth_aggregation(): void
    {
        $server = \App\Models\MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Core',
            'host' => '10.0.0.1',
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $t1 = now()->startOfSecond();
        $t2 = $t1->copy()->addSecond();

        foreach ([[$t1, 50_000_000, 10_000_000], [$t2, 100_000_000, 20_000_000]] as [$at, $down, $up]) {
            \App\Models\WanBandwidthSample::query()->create([
                'tenant_id' => 1,
                'mikrotik_server_id' => $server->id,
                'interface_name' => 'ether1',
                'sampled_at' => $at,
                'rate_in_bps' => $down,
                'rate_out_bps' => $up,
                'bytes_in' => 0,
                'bytes_out' => 0,
            ]);
        }

        $chart = BandwidthCollectionService::aggregateWanLiveMbpsPerSecond(1, 5, 120);

        $this->assertCount(2, $chart['labels']);
        $this->assertEqualsWithDelta(50.0, $chart['download_mbps'][0], 0.01);
        $this->assertEqualsWithDelta(100.0, $chart['download_mbps'][1], 0.01);
    }

    public function test_wan_per_interface_chart_series(): void
    {
        $server = \App\Models\MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Core',
            'host' => '10.0.0.1',
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $t = now()->startOfSecond();
        foreach (['ether1', 'ether2'] as $if) {
            \App\Models\WanBandwidthSample::query()->create([
                'tenant_id' => 1,
                'mikrotik_server_id' => $server->id,
                'interface_name' => $if,
                'sampled_at' => $t,
                'rate_in_bps' => $if === 'ether1' ? 80_000_000 : 20_000_000,
                'rate_out_bps' => 5_000_000,
                'bytes_in' => 0,
                'bytes_out' => 0,
            ]);
        }

        $chart = BandwidthCollectionService::aggregateWanInterfacesMbpsPerSecond(1, 5, 120);

        $this->assertCount(2, $chart['series']);
        $labels = array_column($chart['series'], 'label');
        $this->assertContains('Core · ether1', $labels);
        $this->assertContains('Core · ether2', $labels);

        $compare = BandwidthCollectionService::aggregateLiveComparisonChart(1, 5, 120);
        $this->assertCount(2, $compare['wan_series']);
    }

    public function test_live_per_second_bandwidth_aggregation(): void
    {
        $customer = $this->customer();
        $t1 = now()->startOfSecond();
        $t2 = $t1->copy()->addSecond();

        foreach ([
            [$t1, 10_000_000, 2_000_000],
            [$t2, 20_000_000, 4_000_000],
        ] as [$at, $down, $up]) {
            BandwidthSample::query()->create([
                'tenant_id' => 1,
                'customer_id' => $customer->id,
                'session_key' => 'test-session',
                'username' => 'ppp-user',
                'sampled_at' => $at,
                'rate_in_bps' => $down,
                'rate_out_bps' => $up,
                'bytes_in' => 0,
                'bytes_out' => 0,
            ]);
        }

        $chart = BandwidthCollectionService::aggregateLiveMbpsPerSecond(1, 5, 120);

        $this->assertCount(2, $chart['labels']);
        $this->assertEqualsWithDelta(10.0, $chart['download_mbps'][0], 0.01);
        $this->assertEqualsWithDelta(20.0, $chart['download_mbps'][1], 0.01);
    }

    public function test_hourly_bandwidth_aggregation(): void
    {
        $customer = $this->customer();
        $hour = now()->startOfHour();

        foreach ([20_000_000, 40_000_000] as $i => $rateIn) {
            BandwidthSample::query()->create([
                'tenant_id' => 1,
                'customer_id' => $customer->id,
                'session_key' => 'test-session',
                'username' => 'ppp-user',
                'sampled_at' => $hour->copy()->addMinutes($i * 10),
                'rate_in_bps' => $rateIn,
                'rate_out_bps' => (int) ($rateIn / 4),
                'bytes_in' => 0,
                'bytes_out' => 0,
            ]);
        }

        $chart = BandwidthCollectionService::aggregateHourlyMbps(1, 24);

        $this->assertNotEmpty($chart['labels']);
        $this->assertCount(1, $chart['download_mbps']);
        $this->assertEqualsWithDelta(30.0, $chart['download_mbps'][0], 0.1);
        $this->assertEqualsWithDelta(7.5, $chart['upload_mbps'][0], 0.1);
    }

    public function test_concurrent_session_abuse_alert(): void
    {
        config(['bandwidth.max_concurrent_sessions' => 1]);
        $customer = $this->customer();

        foreach (['sess-a', 'sess-b'] as $key) {
            PppSessionLog::query()->create([
                'tenant_id' => 1,
                'customer_id' => $customer->id,
                'session_key' => $key,
                'username' => 'user1',
                'status' => 'active',
                'started_at' => now(),
                'bytes_in' => 0,
                'bytes_out' => 0,
            ]);
        }

        $created = (new AbuseDetectionService)->evaluateCustomer($customer);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('bandwidth_abuse_alerts', [
            'customer_id' => $customer->id,
            'alert_type' => BandwidthAbuseAlert::TYPE_CONCURRENT_SESSIONS,
        ]);
    }

    public function test_daily_usage_formatting(): void
    {
        $customer = $this->customer();

        BandwidthUsageDaily::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'usage_date' => today(),
            'bytes_in' => 5 * 1073741824,
            'bytes_out' => 1073741824,
            'peak_rate_in_bps' => 52_000_000,
            'online_seconds' => 3600,
            'session_count' => 2,
        ]);

        $this->assertSame('5 GB', BandwidthUsageDaily::formatBytes(5 * 1073741824));
        $this->assertSame('52 Mbps', BandwidthUsageDaily::formatBps(52_000_000));
    }

    public function test_collect_bandwidth_command_respects_disabled_flag(): void
    {
        config(['bandwidth.collection_enabled' => false]);

        $this->artisan('isp:collect-bandwidth')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }
}
