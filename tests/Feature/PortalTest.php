<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_portal_login_form(): void
    {
        $this->get(route('portal.login'))
            ->assertOk()
            ->assertSee('Customer portal', false);
    }

    public function test_customer_can_log_in_with_phone_and_portal_password(): void
    {
        $customer = $this->makeCustomerWithPortal('secret-portal');

        $this->post(route('portal.login.store'), [
            'login' => $customer->phone,
            'password' => 'secret-portal',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_customer_sees_own_invoices_only(): void
    {
        $a = $this->makeCustomerWithPortal('p1');
        $b = $this->makeCustomerWithPortal('p2');

        $invA = Invoice::query()->create([
            'customer_id' => $a->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $invB = Invoice::query()->create([
            'customer_id' => $b->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 200,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($a, 'customer')
            ->get(route('portal.invoices.show', $invA))
            ->assertOk();

        $this->actingAs($a, 'customer')
            ->get(route('portal.invoices.show', $invB))
            ->assertNotFound();
    }

    private function makeCustomerWithPortal(string $plainPassword): Customer
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Portal User',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make($plainPassword),
        ]);
    }
}
