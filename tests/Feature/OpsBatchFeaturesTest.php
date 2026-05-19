<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikSessionAlert;
use App\Models\Package;
use App\Models\PendingGatewayPayment;
use App\Services\Collector\CollectorVisitsReportService;
use App\Services\Hr\HrPayrollHubService;
use App\Services\Payments\GatewayReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsBatchFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_reconciliation_snapshot(): void
    {
        $snapshot = app(GatewayReconciliationService::class)->snapshot();
        $this->assertArrayHasKey('by_gateway', $snapshot);
        $this->assertArrayHasKey('pending_count', $snapshot);
    }

    public function test_hr_payroll_hub_snapshot(): void
    {
        $snapshot = app(HrPayrollHubService::class)->snapshot();
        $this->assertArrayHasKey('active_employees', $snapshot);
        $this->assertArrayHasKey('staff_users', $snapshot);
    }

    public function test_collector_visits_report_empty(): void
    {
        $report = app(CollectorVisitsReportService::class)->report();
        $this->assertSame(0, $report['visit_count']);
        $this->assertSame([], $report['leaderboard']);
    }

    public function test_collector_pwa_redirect_requires_auth(): void
    {
        $this->get('/collector')->assertRedirect();
    }

    public function test_session_alert_suspend_service(): void
    {
        $customer = $this->makeCustomer();
        $alert = MikrotikSessionAlert::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'alert_type' => MikrotikSessionAlert::TYPE_OVERDUE_ONLINE,
            'severity' => 'warning',
            'login' => 'testuser',
            'message' => 'Test',
        ]);

        app(\App\Services\Mikrotik\MikrotikSessionAlertService::class)->suspendFromAlert($alert);

        $customer->refresh();
        $this->assertSame('suspended', $customer->network_access_state);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    private function makeCustomer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Test',
            'phone' => '01710002222',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }
}
