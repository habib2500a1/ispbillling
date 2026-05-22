<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\BillingFundFlowCsvExporter;
use App\Services\Billing\BillingFundFlowService;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingFundFlowReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_splits_invoice_and_wallet_from_meta(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Test User',
            'phone' => '01710000099',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        Payment::withoutEvents(function () use ($customer): void {
            Payment::query()->create([
                'tenant_id' => 1,
                'customer_id' => $customer->id,
                'amount' => 1000,
                'method' => 'cash',
                'status' => 'completed',
                'payment_type' => PaymentType::PAYMENT,
                'paid_at' => now(),
                'meta' => [
                    'processed' => true,
                    'invoice_applied' => 700,
                    'wallet_credit' => 300,
                ],
            ]);
        });

        $report = app(BillingFundFlowService::class)->report(now(), now());

        $this->assertSame(1000.0, $report['summary']['total_collected']);
        $this->assertSame(700.0, $report['summary']['to_invoice']);
        $this->assertSame(300.0, $report['summary']['to_wallet']);
        $this->assertSame(700.0, $report['rows'][0]['to_invoice']);
        $this->assertSame(300.0, $report['rows'][0]['to_wallet']);
        $this->assertStringContainsString('Bill', $report['rows'][0]['destination']);
    }

    public function test_csv_export_includes_summary_and_payment_rows(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Export User',
            'phone' => '01710000088',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        Payment::withoutEvents(function () use ($customer): void {
            Payment::query()->create([
                'tenant_id' => 1,
                'customer_id' => $customer->id,
                'amount' => 500,
                'method' => 'cash',
                'status' => 'completed',
                'payment_type' => PaymentType::PAYMENT,
                'paid_at' => now(),
                'meta' => ['processed' => true, 'invoice_applied' => 500],
            ]);
        });

        $response = app(BillingFundFlowCsvExporter::class)->download(now(), now());
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Bill money trail', $csv);
        $this->assertStringContainsString('Export User', $csv);
        $this->assertStringContainsString('Payment details', $csv);
    }

    public function test_admin_role_can_access_fund_flow_page(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('admin');

        $this->actingAs($user);
        $this->assertTrue(\App\Filament\Pages\BillingFundFlowReport::canAccess());
        $this
            ->get(\App\Filament\Pages\BillingFundFlowReport::getUrl())
            ->assertOk();
    }

    public function test_fund_flow_page_loads_for_staff_with_billing_access(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(\App\Filament\Pages\BillingFundFlowReport::getUrl())
            ->assertOk()
            ->assertSee('where money goes', false)
            ->assertSee('Export CSV', false);
    }
}
