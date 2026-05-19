<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalPackageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_only_public_catalog_packages(): void
    {
        Package::query()->create([
            'name' => 'Hidden Plan',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
            'show_on_website' => false,
            'tenant_id' => 1,
        ]);

        Package::query()->create([
            'name' => 'Public Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
            'show_on_website' => true,
            'tenant_id' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Public Plan')
            ->assertDontSee('Hidden Plan');
    }

    public function test_package_change_blocked_when_open_balance(): void
    {
        $current = Package::query()->create([
            'name' => '5M',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
            'show_on_website' => true,
            'tenant_id' => 1,
        ]);

        $bigger = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
            'show_on_website' => true,
            'tenant_id' => 1,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Up',
            'phone' => '01790001122',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $current->id,
            'tenant_id' => 1,
            'portal_password' => Hash::make('secret'),
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'tenant_id' => 1,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.packages.request'), ['package_id' => $bigger->id])
            ->assertSessionHasErrors('package_id');
    }
}
