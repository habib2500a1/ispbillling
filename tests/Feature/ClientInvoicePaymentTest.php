<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientInvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payables_lists_all_due_invoices(): void
    {
        config(['bill_payment.allow_partial' => false, 'bkash.enabled' => false]);

        $customer = $this->customerWithDueInvoice(500);

        Sanctum::actingAs($customer, ['customer-app']);

        $this->getJson('/api/v1/customer/bills/payables')
            ->assertOk()
            ->assertJsonPath('total_due', 500)
            ->assertJsonPath('require_full_payment', true)
            ->assertJsonPath('line_on_when_due_cleared', true)
            ->assertJsonCount(1, 'due_invoices');
    }

    public function test_payables_includes_prepay_quotes_for_one_two_three_months(): void
    {
        config(['bill_payment.prepay_enabled' => true, 'bill_payment.prepay_quick_months' => [1, 2, 3]]);

        $customer = $this->customerWithDueInvoice(500);

        Sanctum::actingAs($customer, ['customer-app']);

        $this->getJson('/api/v1/customer/bills/payables')
            ->assertOk()
            ->assertJsonPath('prepay.enabled', true)
            ->assertJsonPath('prepay.monthly_rate', 500)
            ->assertJsonPath('prepay.quotes.1.total_amount', 1000)
            ->assertJsonPath('prepay.quotes.2.total_amount', 1500)
            ->assertJsonPath('prepay.quotes.3.total_amount', 2000);
    }

    public function test_prepay_initiate_validates_months_and_gateway(): void
    {
        config(['bill_payment.prepay_enabled' => true, 'bkash.enabled' => false]);

        $customer = $this->customerWithDueInvoice(500);

        Sanctum::actingAs($customer, ['customer-app']);

        $this->postJson('/api/v1/customer/bills/prepay', [
            'months' => 2,
            'gateway' => 'bkash',
        ])->assertStatus(422);
    }

    public function test_initiate_rejects_manual_amount(): void
    {
        config(['bill_payment.allow_partial' => false, 'bkash.enabled' => false]);

        $customer = $this->customerWithDueInvoice(300);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->firstOrFail();

        Sanctum::actingAs($customer, ['customer-app']);

        $this->postJson("/api/v1/customer/bills/{$invoice->id}/pay", [
            'gateway' => 'bkash',
            'amount' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    private function customerWithDueInvoice(float $due): Customer
    {
        $package = Package::query()->create([
            'name' => 'Test Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => $due,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Due Client',
            'phone' => '01711112223',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'status' => 'open',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => $start,
            'period_end' => $end,
            'subtotal' => $due,
            'total' => $due,
            'amount_paid' => 0,
        ]);

        return $customer;
    }
}
