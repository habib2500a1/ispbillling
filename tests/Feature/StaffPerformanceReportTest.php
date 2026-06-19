<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\StaffPerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_legacy_received_by_for_staff_collection_report(): void
    {
        $staff = User::factory()->create(['name' => 'Habib', 'tenant_id' => 1]);
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Client',
            'phone' => '01710000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'joined_at' => now()->toDateString(),
            'meta' => ['registered_by_id' => $staff->id],
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'bkash',
            'status' => 'completed',
            'paid_at' => now(),
            'meta' => ['received_by' => 'Habib'],
        ]);

        $report = app(StaffPerformanceReportService::class)->dashboard(1);

        $this->assertSame(500.0, $report['today']['total']);
        $this->assertSame('Habib', $report['today']['by_staff'][0]['name']);
        $this->assertSame($staff->id, $report['today']['by_staff'][0]['staff_id']);
        $this->assertSame(1, $report['new_lines_month']['total']);
    }

    public function test_staff_dashboard_includes_performance_block(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('super-admin');

        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'staff_performance' => [
                    'today' => ['total', 'count', 'by_staff'],
                    'month' => ['total', 'count', 'by_staff'],
                    'new_lines_month' => ['total', 'by_staff'],
                ],
            ]);
    }
}
