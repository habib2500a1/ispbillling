<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomerInvoice(float $total = 1000): array
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => $total,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Pay',
            'phone' => '01730000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'account_balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        return [$customer, $invoice];
    }

    public function test_partial_payment_updates_invoice_status(): void
    {
        [$customer, $invoice] = $this->seedCustomerInvoice(1000);

        Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::PAYMENT,
        ]);

        $invoice->refresh();
        $this->assertSame(400.0, (float) $invoice->amount_paid);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_overpayment_credits_wallet(): void
    {
        config(['payments.overpayment_to_wallet' => true]);
        [$customer, $invoice] = $this->seedCustomerInvoice(500);

        Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 600,
            'method' => 'bkash',
            'gateway' => 'bkash',
            'gateway_transaction_id' => 'TRX-OVER-1',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $invoice->refresh();
        $customer->refresh();

        $this->assertSame(500.0, (float) $invoice->amount_paid);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(100.0, (float) $customer->account_balance);
    }

    public function test_gateway_webhook_is_idempotent(): void
    {
        config(['payments.gateways.nagad.enabled' => true, 'payments.gateways.nagad.webhook_secret' => 'test-secret']);
        [$customer, $invoice] = $this->seedCustomerInvoice(300);

        $payload = [
            'transaction_id' => 'NAGAD-123',
            'amount' => 300,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
        ];

        $this->postJson('/api/webhooks/payments/nagad', $payload, ['X-Webhook-Secret' => 'test-secret'])
            ->assertOk();

        $this->postJson('/api/webhooks/payments/nagad', $payload, ['X-Webhook-Secret' => 'test-secret'])
            ->assertOk();

        $this->assertSame(1, Payment::query()->where('gateway_transaction_id', 'NAGAD-123')->count());
        $invoice->refresh();
        $this->assertSame(300.0, (float) $invoice->amount_paid);
    }

    public function test_refund_reduces_invoice_paid(): void
    {
        [$customer, $invoice] = $this->seedCustomerInvoice(800);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 800,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $invoice->refresh();
        $this->assertSame(800.0, (float) $invoice->amount_paid);

        PaymentProcessor::recordRefund($payment, 200, 'Partial refund');

        $invoice->refresh();
        $this->assertSame(600.0, (float) $invoice->amount_paid);
        $this->assertDatabaseHas('payments', ['payment_type' => PaymentType::REFUND]);
    }

    public function test_receipt_number_generated_on_create(): void
    {
        [$customer] = $this->seedCustomerInvoice();

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'amount' => 100,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->assertNotNull($payment->receipt_number);
        $this->assertStringStartsWith('RCP-', $payment->receipt_number);
    }

    public function test_wallet_deposit_increases_balance(): void
    {
        [$customer] = $this->seedCustomerInvoice();

        Payment::query()->create([
            'customer_id' => $customer->id,
            'amount' => 250,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::WALLET_DEPOSIT,
        ]);

        $this->assertSame(250.0, (float) $customer->fresh()->account_balance);
    }
}
