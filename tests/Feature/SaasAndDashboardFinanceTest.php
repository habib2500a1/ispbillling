<?php

namespace Tests\Feature;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\SaasOperator;
use App\Models\User;
use App\Services\Dashboard\DashboardFinanceService;
use App\Services\Saas\OperatorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaasAndDashboardFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_total_bill_collection_and_due_percent(): void
    {
        CustomersInfo::create([
            'customer_unique_id' => 'CID-BILL-1',
            'customer_name' => 'Bill Client',
            'mobile' => '01700000011',
            'status' => 'active',
        ]);

        BillingInfo::create([
            'customer_bill_unique_id' => 'CID-BILL-1',
            'monthly_rent' => 1000,
            'additional_charge' => 50,
            'vat' => 50,
            'discount' => 0,
            'due_amount' => 400,
            'paid_amount' => 0,
        ]);

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CID-BILL-1',
            'collection_amount' => 600,
            'collection_date' => now()->toDateString(),
        ]);

        $summary = app(DashboardFinanceService::class)->summary();

        $this->assertEquals(1100.0, $summary['bill']);
        $this->assertEquals(600.0, $summary['collection']);
        $this->assertEquals(400.0, $summary['due']);
        $this->assertEquals(60.0, $summary['collection_pct']);
        $this->assertEquals(40.0, $summary['due_pct']);

        CollectionSummary::create([
            'customer_collection_unique_id' => 'CID-BILL-1',
            'collection_amount' => 250,
            'collection_date' => now()->subHour(),
            'collected_by' => 'demo@anetbd.com',
        ]);

        $recent = app(DashboardFinanceService::class)->recentPayments(5);
        $this->assertGreaterThanOrEqual(2, $recent->count());
        $this->assertEquals(250.0, (float) $recent->first()->collection_amount);
    }

    public function test_platform_owner_can_sell_operator_who_cannot_resell(): void
    {
        Role::create(['name' => 'Super Admin']);
        Permission::create(['name' => 'dashboard']);
        Permission::create(['name' => 'all-customer']);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@isp.com',
            'mobile' => '01711122201',
            'password' => bcrypt('password'),
        ]);
        $owner->assignRole('Super Admin');

        $this->actingAs($owner);
        $this->assertTrue(canSellSaas());

        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Buyer ISP',
            'contact_name' => 'Buyer Admin',
            'email' => 'buyer@isp.com',
            'password' => 'password12',
            'plan' => 'pro',
        ]);

        $this->assertDatabaseHas('saas_operators', [
            'id' => $operator->id,
            'company' => 'Buyer ISP',
            'can_resell' => 0,
        ]);

        $buyer = User::where('email', 'buyer@isp.com')->first();
        $this->assertTrue($buyer->hasRole('Operator'));
        $this->assertFalse($buyer->hasRole('Super Admin'));
        $this->assertFalse($buyer->can('saas-sell'));

        $this->actingAs($buyer);
        $this->assertFalse(canSellSaas());

        $this->get('http://localhost/admin/saas-operators')->assertForbidden();
    }

    public function test_monthly_bill_generation_writes_payment_summary(): void
    {
        CustomersInfo::create([
            'customer_unique_id' => 'CID-GEN-1',
            'customer_name' => 'Gen Client',
            'mobile' => '01700000022',
            'status' => 'active',
        ]);

        BillingInfo::create([
            'customer_bill_unique_id' => 'CID-GEN-1',
            'monthly_rent' => 800,
            'additional_charge' => 0,
            'vat' => 0,
            'discount' => 0,
            'due_amount' => 800,
            'paid_amount' => 0,
            'billing_day' => now()->day,
        ]);

        \App\Models\OfficialInfo::create([
            'customer_office_unique_id' => 'CID-GEN-1',
            'continue_bill' => true,
        ]);

        app(\App\Http\Controllers\ScheduledTasksController::class)->createMonthlyBill((int) now()->day, false);

        $this->assertTrue(
            \App\Models\PaymentSummary::query()
                ->where('customer_payment_unique_id', 'CID-GEN-1')
                ->where('monthly_rent', 800)
                ->exists(),
            'Monthly bill generation should write a payment summary.'
        );
    }
}
