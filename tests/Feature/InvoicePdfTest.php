<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_invoice_pdf(): void
    {
        $invoice = $this->makeInvoice();

        $this->get(route('invoices.pdf', $invoice))
            ->assertRedirect();
    }

    public function test_authenticated_user_receives_pdf(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoice();

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_customer_can_download_own_invoice_pdf(): void
    {
        $package = Package::query()->create([
            'name' => '20 Mbps',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 1500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'PDF Customer',
            'phone' => '01912222222',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
            'portal_password' => Hash::make('secret'),
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 1500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('portal.invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function makeInvoice(): Invoice
    {
        $package = Package::query()->create([
            'name' => '20 Mbps',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 1500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'PDF Customer',
            'phone' => '01911111111',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 1500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);
    }
}
