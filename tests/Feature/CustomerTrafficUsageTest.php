<?php

namespace Tests\Feature;

use App\Models\CustomerTrafficUsage;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Services\Bandwidth\CustomerTrafficUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerTrafficUsageTest extends TestCase
{
    use RefreshDatabase;

    private function secret(): PPPSecrets
    {
        $ppp = PPPSecrets::create([
            'username' => 'habibfree',
            'password' => 'secret',
            'service' => 'pppoe',
            'status' => 'active',
            'router_name' => null,
        ]);

        CustomersInfo::create([
            'customer_unique_id' => 'FCNET100',
            'customer_name' => 'habibfree',
            'mobile' => '8801841558023',
            'status' => 'active',
            'ppp_user_id' => $ppp->id,
        ]);

        return $ppp->fresh();
    }

    public function test_first_snapshot_fills_session_day_and_month(): void
    {
        $ppp = $this->secret();
        $usage = app(CustomerTrafficUsageService::class);

        $usage->apply($ppp, [
            'name' => 'habibfree',
            'uptime' => '1h',
            'bytes-in' => 100 * 1024 * 1024,
            'bytes-out' => 400 * 1024 * 1024,
        ]);

        $shown = $usage->presentForSecret($ppp->fresh('trafficUsage'));
        $this->assertTrue($shown['online']);
        $this->assertSame('This session', $shown['live_or_last_title']);
        $this->assertSame($shown['session_total'], $shown['day_total']);
        $this->assertSame($shown['session_total'], $shown['month_total']);
        $this->assertSame(500 * 1024 * 1024, $shown['session_total']);
        $this->assertSame('500.00 MB', $shown['month_total_label']);
    }

    public function test_second_snapshot_adds_only_the_delta(): void
    {
        $ppp = $this->secret();
        $usage = app(CustomerTrafficUsageService::class);

        $usage->apply($ppp, ['uptime' => '10m', 'bytes-in' => 100, 'bytes-out' => 200]);
        $usage->apply($ppp->fresh('trafficUsage'), ['uptime' => '11m', 'bytes-in' => 150, 'bytes-out' => 260]);

        $row = CustomerTrafficUsage::query()->where('ppp_secret_id', $ppp->id)->firstOrFail();
        $this->assertSame(150, (int) $row->session_rx_bytes);
        $this->assertSame(260, (int) $row->session_tx_bytes);
        $this->assertSame(150, (int) $row->day_rx_bytes);
        $this->assertSame(260, (int) $row->day_tx_bytes);
    }

    public function test_reconnect_keeps_last_online_and_still_adds_to_month(): void
    {
        $ppp = $this->secret();
        $usage = app(CustomerTrafficUsageService::class);

        $usage->apply($ppp, ['uptime' => '5m', 'bytes-in' => 1000, 'bytes-out' => 2000]);
        $usage->apply($ppp->fresh('trafficUsage'), null);
        $offline = $usage->presentForSecret($ppp->fresh('trafficUsage'));
        $this->assertFalse($offline['online']);
        $this->assertSame('Last online', $offline['live_or_last_title']);
        $this->assertSame(3000, $offline['live_or_last_total']);
        $this->assertSame(3000, $offline['month_total']);

        $usage->apply($ppp->fresh('trafficUsage'), ['uptime' => '1m', 'bytes-in' => 40, 'bytes-out' => 60]);
        $again = $usage->presentForSecret($ppp->fresh('trafficUsage'));
        $this->assertSame('This session', $again['live_or_last_title']);
        $this->assertSame(100, $again['live_or_last_total']);
        $this->assertSame(3100, $again['month_total']);
        $this->assertSame(3000, $again['last_session_total']);
    }

    public function test_month_end_reset_clears_monthly_cache_but_keeps_last_session(): void
    {
        $ppp = $this->secret();
        $usage = app(CustomerTrafficUsageService::class);
        $usage->apply($ppp, ['uptime' => '2m', 'bytes-in' => 80, 'bytes-out' => 20]);

        $row = CustomerTrafficUsage::query()->where('ppp_secret_id', $ppp->id)->firstOrFail();
        $row->month_key = now()->subMonth()->format('Y-m');
        $row->save();
        Cache::put(CustomerTrafficUsageService::CACHE_PREFIX.$ppp->id, ['month_key' => $row->month_key], 60);

        $this->artisan('app:reset-traffic-month')->assertSuccessful();

        $row->refresh();
        $this->assertSame(now()->format('Y-m'), $row->month_key);
        $this->assertSame(0, (int) $row->month_rx_bytes);
        $this->assertSame(0, (int) $row->month_tx_bytes);
        $this->assertSame(80, (int) $row->session_rx_bytes);

        $shown = $usage->presentForSecret($ppp->fresh('trafficUsage'));
        $this->assertSame(0, $shown['month_total']);
        $this->assertSame(100, $shown['live_or_last_total']);
    }

    public function test_stale_month_key_reads_as_zero_before_job_runs(): void
    {
        $ppp = $this->secret();
        $usage = app(CustomerTrafficUsageService::class);
        $usage->apply($ppp, ['uptime' => '2m', 'bytes-in' => 10, 'bytes-out' => 10]);

        $row = CustomerTrafficUsage::query()->where('ppp_secret_id', $ppp->id)->firstOrFail();
        $row->month_key = '2020-01';
        $row->month_rx_bytes = 999;
        $row->month_tx_bytes = 999;
        $row->save();
        Cache::forget(CustomerTrafficUsageService::CACHE_PREFIX.$ppp->id);

        $shown = $usage->presentForSecret($ppp->fresh('trafficUsage'));
        $this->assertSame(0, $shown['month_total']);
        $this->assertSame(now()->format('Y-m'), $shown['month_key']);
    }

    public function test_format_bytes_prefers_gb(): void
    {
        $this->assertSame('1.50 GB', CustomerTrafficUsageService::formatBytes((int) (1.5 * 1024 * 1024 * 1024)));
        $this->assertSame('20.00 MB', CustomerTrafficUsageService::formatBytes(20 * 1024 * 1024));
        $this->assertSame('0 B', CustomerTrafficUsageService::formatBytes(0));
    }
}
