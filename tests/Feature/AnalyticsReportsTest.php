<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsReports;
use App\Filament\Pages\ReportsHub;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\AnalyticsReportService;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_summary_returns_expected_keys(): void
    {
        $service = app(AnalyticsReportService::class);
        $summary = $service->summary(now()->startOfMonth(), now()->endOfMonth(), 1);

        $this->assertArrayHasKey('collected', $summary);
        $this->assertArrayHasKey('outstanding', $summary);
        $this->assertArrayHasKey('active_subscribers', $summary);
        $this->assertArrayHasKey('online_now', $summary);
    }

    public function test_collection_report_totals_match_payments(): void
    {
        $package = Package::query()->create([
            'name' => 'Report Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Report Cust',
            'phone' => '01710009988',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 150,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $from = Carbon::parse(now()->startOfMonth());
        $to = Carbon::parse(now()->endOfMonth());
        $report = app(AnalyticsReportService::class)->collectionReport($from, $to, 1);

        $this->assertEquals(150.0, $report['total']);
        $this->assertNotEmpty($report['by_method']);
    }

    public function test_isp_admin_can_open_reports_hub_and_analytics(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ReportsHub::class)
            ->assertSuccessful()
            ->assertSee('Collection report');

        Livewire::actingAs($user)
            ->test(AnalyticsReports::class)
            ->assertSuccessful()
            ->assertSee('Collection report');

        Livewire::actingAs($user)
            ->test(AnalyticsReports::class)
            ->call('setActiveTab', 'due')
            ->assertSet('activeTab', 'due')
            ->assertSee('Due report');
    }

    public function test_reports_hub_page_loads_via_http(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/reports-hub')
            ->assertOk()
            ->assertSee('Collection report');
    }
}
