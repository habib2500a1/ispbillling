<?php

namespace Tests\Feature;

use App\Livewire\BillingAdjustments;
use App\Livewire\MainSiteSetup;
use App\Livewire\PaymentCollection;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\MainSiteData;
use App\Models\User;
use App\Services\Dashboard\DashboardFinanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('payment-collection', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function customer(string $id = 'FCNET200', float $due = 500, float $discount = 0, float $advance = 0): CustomersInfo
    {
        $customer = CustomersInfo::create([
            'customer_unique_id' => $id,
            'customer_name' => 'Bill Client '.$id,
            'mobile' => '01700000'.substr($id, -3),
            'status' => 'active',
        ]);
        BillingInfo::create([
            'customer_bill_unique_id' => $id,
            'monthly_rent' => 500,
            'due_amount' => $due,
            'paid_amount' => 0,
            'total_amount' => $due,
            'discount' => $discount,
            'advance' => $advance,
            'auto_disable_date' => now()->addMonth()->toDateString(),
            'auto_disable_month' => 1,
        ]);

        return $customer;
    }

    public function test_site_settings_saves_monthly_bill_day_and_clock(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MainSiteSetup::class)
            ->set('activeTab', 'billing')
            ->set('bill_generate_day', 7)
            ->set('bill_generate_at', '21:15')
            ->set('bill_generate_on', true)
            ->set('bill_generate_mode', 'global')
            ->call('save', 'billing')
            ->assertHasNoErrors();

        $this->assertSame(7, (int) MainSiteData::getValue('monthly_bill_day'));
        $this->assertSame('global', MainSiteData::getValue('monthly_bill_mode'));
    }

    public function test_global_bill_day_skips_other_dates(): void
    {
        MainSiteData::setValue('monthly_bill_mode', 'global');
        MainSiteData::setValue('monthly_bill_day', 15);
        $this->customer('FCNET201', 500);

        Carbon::setTestNow(Carbon::parse('2026-08-14 23:45', 'Asia/Dhaka'));
        $this->artisan('cpagol:generate-monthly-bills')->assertSuccessful();
        $this->assertDatabaseMissing('payment_summaries', [
            'customer_payment_unique_id' => 'FCNET201',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-15 23:45', 'Asia/Dhaka'));
        $this->artisan('cpagol:generate-monthly-bills')->assertSuccessful();
        $this->assertDatabaseHas('payment_summaries', [
            'customer_payment_unique_id' => 'FCNET201',
        ]);

        Carbon::setTestNow();
    }

    public function test_collection_can_apply_discount_and_advance(): void
    {
        $this->actingAs($this->admin());
        $this->customer('FCNET202', 500);

        Livewire::test(PaymentCollection::class)
            ->call('selectCustomer', encrypt('FCNET202'))
            ->set('apply_discount', 50)
            ->set('apply_advance', 20)
            ->set('paid_amount', 200)
            ->set('expire_date', now()->addMonth()->toDateString())
            ->call('paymentSubmit')
            ->assertHasNoErrors();

        $bill = BillingInfo::query()->where('customer_bill_unique_id', 'FCNET202')->first();
        $this->assertEquals(50.0, (float) $bill->discount);
        $this->assertEquals(20.0, (float) $bill->advance);
        $this->assertEquals(230.0, (float) $bill->due_amount);
        $this->assertEquals(200.0, (float) $bill->paid_amount);
    }

    public function test_dashboard_and_discount_page_show_adjustments(): void
    {
        $this->actingAs($this->admin());
        $this->customer('FCNET203', 400, 80, 25);

        $summary = app(DashboardFinanceService::class)->summary();
        $this->assertEquals(80.0, $summary['discount']);
        $this->assertEquals(25.0, $summary['advance']);

        $this->get(route('billing.discounts'))->assertOk()->assertSee('FCNET203')->assertSee('80');
        $this->get(route('billing.advances'))->assertOk()->assertSee('FCNET203')->assertSee('25');

        Livewire::test(BillingAdjustments::class)
            ->assertSee('FCNET203');
    }
}
