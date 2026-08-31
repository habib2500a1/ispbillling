<?php

namespace Tests\Feature;

use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\ScheduledTasksController;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use App\Models\User;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Ready-to-play billing loop: disable date, pay → status ON, monthly bill by billing_day.
 */
class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomer(array $billingOverrides = [], array $customerOverrides = []): CustomersInfo
    {
        $user = User::factory()->create();

        RouterList::create([
            'router_name' => 'TestRouter',
            'ip_address' => '10.0.0.1',
            'username' => 'admin',
            'password' => 'secret',
            'action' => 'connected',
            'ssh_port' => '22',
        ]);

        $ppp = PPPSecrets::create([
            'username' => 'ppp_test_user',
            'password' => 'pass',
            'service' => 'pppoe',
            'profile' => '10Mbps',
            'router_name' => 'TestRouter',
            'status' => 'active',
        ]);

        $customer = CustomersInfo::create(array_merge([
            'customer_unique_id' => 'CID-1001',
            'customer_name' => 'Test Customer',
            'mobile' => '01700000001',
            'email' => 'cust@example.com',
            'status' => 'active',
            'disable_count' => 0,
            'ppp_user_id' => $ppp->id,
        ], $customerOverrides));

        OfficialInfo::create([
            'customer_office_unique_id' => $customer->customer_unique_id,
            'continue_bill' => true,
        ]);

        BillingInfo::create(array_merge([
            'customer_bill_unique_id' => $customer->customer_unique_id,
            'monthly_rent' => 500,
            'additional_charge' => 0,
            'vat' => 0,
            'discount' => 0,
            'advance' => 0,
            'previous_due' => 0,
            'due_amount' => 500,
            'paid_amount' => 0,
            'total_amount' => 500,
            'auto_disable' => true,
            'auto_disable_date' => Carbon::today()->subDay()->toDateString(),
            'auto_disable_month' => 1,
            'billing_day' => (int) Carbon::today()->day,
            'grace_period_days' => 0,
        ], $billingOverrides));

        return $customer->fresh(['billing', 'pppUser', 'official']);
    }

    public function test_successful_full_payment_sets_active_and_extends_disable_date(): void
    {
        $customer = $this->seedCustomer();

        $mikrotik = Mockery::mock(MikrotikController::class);
        $mikrotik->shouldReceive('enablePPPSecret')->once();
        $mikrotik->shouldReceive('updatePPPSecret')->once();
        $mikrotik->shouldReceive('singleWrite')->once();
        $this->app->instance(MikrotikController::class, $mikrotik);

        $ok = app(PaymentService::class)->processSuccessPayment(
            $customer,
            500.0,
            'bkash',
            'TRX-TEST-001'
        );

        $this->assertTrue($ok);

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertSame(0, (int) $customer->disable_count);
        $this->assertSame(0.0, (float) $customer->billing->due_amount);
        $this->assertNotNull($customer->billing->auto_disable_date);
        $this->assertTrue(
            Carbon::parse($customer->billing->auto_disable_date)->gte(Carbon::today())
        );
    }

    public function test_partial_payment_without_partial_activation_leaves_inactive(): void
    {
        $customer = $this->seedCustomer([
            'due_amount' => 500,
            'monthly_rent' => 500,
        ]);

        $mikrotik = Mockery::mock(MikrotikController::class);
        $mikrotik->shouldReceive('disablePPPSecret')->once();
        $this->app->instance(MikrotikController::class, $mikrotik);

        $ok = app(PaymentService::class)->processSuccessPayment(
            $customer,
            100.0,
            'bkash',
            'TRX-PARTIAL-001'
        );

        $this->assertTrue($ok);
        $customer->refresh();
        $this->assertSame('inactive', $customer->status);
        $this->assertGreaterThan(0, (float) $customer->billing->due_amount);
    }

    public function test_monthly_bill_generation_for_billing_day_updates_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 12:00:00', 'Asia/Dhaka'));

        $customer = $this->seedCustomer([
            'due_amount' => 0,
            'paid_amount' => 500,
            'previous_due' => 0,
            'billing_day' => 24,
            'auto_disable_date' => '2026-08-24',
        ]);

        app(ScheduledTasksController::class)->createMonthlyBill(billingDay: 24);

        $customer->refresh();
        $this->assertGreaterThan(0, (float) $customer->billing->due_amount);
        $this->assertDatabaseHas('payment_summaries', [
            'customer_payment_unique_id' => 'CID-1001',
        ]);

        Carbon::setTestNow();
    }

    public function test_inactive_customers_do_not_receive_monthly_bills(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', 'Asia/Dhaka'));

        $customer = $this->seedCustomer([
            'billing_day' => 20,
        ], [
            'status' => 'inactive',
        ]);

        $customer->official->update(['continue_bill' => true]);

        app(ScheduledTasksController::class)->createMonthlyBill(billingDay: 20);

        $this->assertDatabaseMissing('payment_summaries', [
            'customer_payment_unique_id' => $customer->customer_unique_id,
        ]);

        Carbon::setTestNow();
    }
}
