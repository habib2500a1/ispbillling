<?php

namespace Tests\Unit;

use App\Models\CallLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallCenter\CallCenterReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallCenterReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_summary_groups_by_user(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'call-report-test'],
            ['name' => 'Call Report ISP', 'is_active' => true],
        );

        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        CallLog::query()->create([
            'tenant_id' => $tenant->id,
            'staff_user_id' => $staff->id,
            'direction' => 'outbound',
            'phone' => '01710001111',
            'status' => 'answered',
            'duration_seconds' => 60,
            'started_at' => now(),
        ]);

        CallLog::query()->create([
            'tenant_id' => $tenant->id,
            'staff_user_id' => $staff->id,
            'direction' => 'inbound',
            'phone' => '01710002222',
            'status' => 'missed',
            'duration_seconds' => 0,
            'started_at' => now(),
        ]);

        $rows = app(CallCenterReportService::class)->staffSummary(now()->startOfDay(), now()->endOfDay());

        $this->assertCount(1, $rows);
        $this->assertSame($staff->id, $rows[0]['staff_user_id']);
        $this->assertSame(2, $rows[0]['total']);
        $this->assertSame(1, $rows[0]['answered']);
        $this->assertSame(1, $rows[0]['missed']);
    }
}
