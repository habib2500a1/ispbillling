<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bkash.enabled' => true,
            'bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'bkash.app_key' => 'test_key',
            'bkash.app_secret' => 'test_secret',
            'bkash.username' => 'test_user',
            'bkash.password' => 'test_pass',
        ]);
    }

    public function test_callback_records_payment_and_credits_invoice(): void
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

        $customer = Customer::query()->create([
            'name' => 'BK Customer',
            'phone' => '01711111111',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $paymentId = 'TR0011TEST123456789';

        Cache::put('bkash_checkout:'.$paymentId, [
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => '500.00',
        ], 3600);

        Http::fake([
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'statusCode' => '0000',
                'id_token' => 'fake-id-token',
            ], 200),
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => $paymentId,
                'trxID' => 'TRXABC123',
                'amount' => '500.00',
                'transactionStatus' => 'Completed',
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $invoice->invoice_number,
            ], 200),
        ]);

        $this->get(route('bkash.callback', ['paymentID' => $paymentId, 'status' => 'success']))
            ->assertRedirect(route('filament.admin.resources.invoices.edit', ['record' => $invoice]));

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'reference' => 'TRXABC123',
            'status' => 'completed',
        ]);

        $invoice->refresh();
        $this->assertSame(500.0, (float) $invoice->amount_paid);
        $this->assertFalse(Cache::has('bkash_checkout:'.$paymentId));
    }

    public function test_initiate_redirects_when_disabled(): void
    {
        config(['bkash.enabled' => false]);

        $user = User::factory()->create();
        $invoice = $this->makeInvoiceForUser();

        $this->actingAs($user)
            ->get(route('bkash.invoice.initiate', $invoice))
            ->assertRedirect(route('filament.admin.resources.invoices.edit', ['record' => $invoice]));
    }

    private function makeInvoiceForUser(): Invoice
    {
        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'C',
            'phone' => '01800000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'amount_paid' => 0,
            'status' => 'open',
        ]);
    }
}
