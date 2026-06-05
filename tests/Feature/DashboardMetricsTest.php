<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Dashboard\SubscriberSegmentMetrics;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_snapshot_returns_expected_keys(): void
    {
        $snapshot = app(DashboardMetricsService::class)->snapshot(1);

        $this->assertArrayHasKey('collected', $snapshot);
        $this->assertArrayHasKey('online_now', $snapshot);
        $this->assertArrayHasKey('open_tickets', $snapshot);
        $this->assertArrayHasKey('mikrotik_total', $snapshot);
    }

    public function test_online_now_is_zero_without_mikrotik_when_stale_flags_exist(): void
    {
        Customer::factory()->create([
            'tenant_id' => 1,
            'status' => CustomerStatus::ACTIVE,
            'is_ppp_online' => true,
            'ppp_last_seen_at' => now(),
        ]);

        Cache::forget('dashboard:subscriber_segments:1');
        Cache::forget('dashboard:snapshot:1');

        $segments = app(SubscriberSegmentMetrics::class)->forTenant(1);

        $this->assertSame(0, $segments['online_now']);
    }

    public function test_revenue_trend_returns_series(): void
    {
        $trend = app(DashboardMetricsService::class)->revenueTrend(7, 1);

        $this->assertCount(7, $trend['labels']);
        $this->assertCount(7, $trend['collected']);
        $this->assertCount(7, $trend['invoiced']);
    }

    public function test_admin_dashboard_renders_command_center(): void
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        \Spatie\Permission\Models\Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk()
            ->assertSee('fi-dashboard-page', false);
    }

    public function test_kpi_grid_has_four_columns(): void
    {
        $grid = app(DashboardMetricsService::class)->kpiGrid(1);

        $this->assertArrayHasKey('columns', $grid);
        $this->assertCount(4, $grid['columns']);
        $this->assertSame('Total customers', $grid['columns'][0]['cards'][0]['label']);
    }

    public function test_dashboard_hub_renders_for_admin(): void
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => 1]);
        \Spatie\Permission\Models\Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(\App\Filament\Pages\DashboardHub::getUrl())
            ->assertOk()
            ->assertSee('Dashboard hub', false)
            ->assertSee('NOC dashboard', false);
    }
}
