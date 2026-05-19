<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalMultiGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_invoice_show_lists_enabled_gateways(): void
    {
        config([
            'bkash.enabled' => true,
            'sslcommerz.enabled' => true,
            'nagad.enabled' => false,
        ]);

        $customer = $this->customer();
        $invoice = $this->openInvoice($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('bKash')
            ->assertSee('SSLCommerz')
            ->assertDontSee('>Nagad<');
    }

    public function test_portal_pay_post_rejects_disabled_gateway(): void
    {
        config(['sslcommerz.enabled' => false, 'bkash.enabled' => true, 'nagad.enabled' => false]);

        $customer = $this->customer();
        $invoice = $this->openInvoice($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.invoices.pay', $invoice), ['gateway' => 'sslcommerz'])
            ->assertRedirect(route('portal.invoices.show', $invoice))
            ->assertSessionHas('danger');
    }

    public function test_portal_pay_validates_gateway_field(): void
    {
        $customer = $this->customer();
        $invoice = $this->openInvoice($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.invoices.pay', $invoice), ['gateway' => 'invalid'])
            ->assertSessionHasErrors('gateway');
    }

    private function customer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        return Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Portal User',
            'phone' => '01710009977',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('secret'),
        ]);
    }

    private function openInvoice(Customer $customer): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);
    }
}
