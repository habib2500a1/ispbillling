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

    public function test_session_alert_resolve_auto_restores_network_when_no_other_open_alerts(): void
    {
        $customer = $this->makeCustomer();
        $customer->forceFill([
            'status' => 'suspended',
            'network_access_state' => 'suspended',
        ])->save();

        $alert = MikrotikSessionAlert::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'alert_type' => MikrotikSessionAlert::TYPE_OVERDUE_ONLINE,
            'severity' => 'warning',
            'login' => 'testuser',
            'message' => 'Test',
        ]);

        $this->assertNull($alert->fresh()->resolved_at);

        app(\App\Services\Mikrotik\MikrotikSessionAlertService::class)->resolve($alert);

        $customer->refresh();
        $alert->refresh();

        $this->assertNotNull($alert->resolved_at);
        $this->assertSame('active', $customer->network_access_state);
        $this->assertSame('active', $customer->status);
    }

    public function test_session_alert_resolve_keeps_suspension_if_another_alert_is_still_open(): void
    {
        $customer = $this->makeCustomer();
        $customer->forceFill([
            'status' => 'suspended',
            'network_access_state' => 'suspended',
        ])->save();

        $alert1 = MikrotikSessionAlert::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'alert_type' => MikrotikSessionAlert::TYPE_OVERDUE_ONLINE,
            'severity' => 'warning',
            'login' => 'testuser',
            'message' => 'Test 1',
        ]);

        $alert2 = MikrotikSessionAlert::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'alert_type' => MikrotikSessionAlert::TYPE_WRONG_ROUTER,
            'severity' => 'warning',
            'login' => 'testuser',
            'message' => 'Test 2',
        ]);

        app(\App\Services\Mikrotik\MikrotikSessionAlertService::class)->resolve($alert1);

        $customer->refresh();
        $alert1->refresh();
        $alert2->refresh();

        $this->assertNotNull($alert1->resolved_at);
        $this->assertNull($alert2->resolved_at);
        $this->assertSame('suspended', $customer->network_access_state);
        $this->assertSame('suspended', $customer->status);
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
