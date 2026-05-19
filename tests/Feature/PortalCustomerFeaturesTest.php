<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalCustomerFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_bills_page(): void
    {
        $customer = $this->makeCustomerWithPortal('portal-pass');

        $this->actingAs($customer, 'customer')
            ->get(route('portal.bills.index'))
            ->assertOk()
            ->assertSee('My bills');
    }

    public function test_customer_can_view_packages_and_profile(): void
    {
        $customer = $this->makeCustomerWithPortal('portal-pass');

        $this->actingAs($customer, 'customer')
            ->get(route('portal.packages.index'))
            ->assertOk()
            ->assertSee('Internet packages');

        $this->actingAs($customer, 'customer')
            ->get(route('portal.profile.index'))
            ->assertOk()
            ->assertSee('Profile management');
    }

    public function test_portal_pay_route_requires_ownership(): void
    {
        config(['bkash.enabled' => false]);
        $a = $this->makeCustomerWithPortal('p1');
        $b = $this->makeCustomerWithPortal('p2');

        $inv = Invoice::query()->create([
            'customer_id' => $b->id,
            'tenant_id' => $b->tenant_id,
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

        $this->actingAs($a, 'customer')
            ->post(route('portal.invoices.pay', $inv), ['gateway' => 'bkash'])
            ->assertNotFound();
    }

    private function makeCustomerWithPortal(string $plainPassword): Customer
    {
        $package = Package::query()->create([
            'name' => 'Portal Plan',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        return Customer::query()->create([
            'name' => 'Portal User',
            'phone' => '017'.fake()->unique()->numerify('########'),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'portal_password' => Hash::make($plainPassword),
        ]);
    }
}
