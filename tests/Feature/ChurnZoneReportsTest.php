<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\User;
use App\Models\Zone;
use App\Services\Reports\AnalyticsReportService;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChurnZoneReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    private function admin(): User
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        return $user;
    }

    public function test_churn_zone_reports_page_loads(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/churn-zone-reports')
            ->assertOk()
            ->assertSee('Zone-wise collection');
    }

    public function test_zone_collection_report_groups_by_zone(): void
    {
        $area = Area::query()->create(['tenant_id' => 1, 'name' => 'Dhaka', 'is_active' => true]);
        $zone = Zone::query()->create(['tenant_id' => 1, 'area_id' => $area->id, 'name' => 'Uttara', 'is_active' => true]);

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Z1',
            'phone' => '01710001101',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'area_id' => $area->id,
            'zone_id' => $zone->id,
        ]);

        $from = Carbon::parse('2026-05-01');
        $to = Carbon::parse('2026-05-31');
        $rows = app(AnalyticsReportService::class)->zoneCollectionReport($from, $to);

        $this->assertNotEmpty($rows);
        $this->assertSame('Uttara', $rows[0]['zone']);
        $this->assertSame(1, $rows[0]['subscribers']);
    }
}
