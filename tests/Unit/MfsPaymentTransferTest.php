<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payments\MfsPaymentTransferService;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MfsPaymentTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_reverses_wrong_customer_and_applies_to_target(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        $wrong = Customer::query()->create([
            'name' => 'Wrong',
            'customer_code' => 'WRONG1',
            'phone' => '01841558023',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'account_balance' => 0,
        ]);

        $right = Customer::query()->create([
            'name' => 'Fariya',
            'customer_code' => '0782',
            'phone' => '01339078960',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'account_balance' => 0,
        ]);

        $invoiceWrong = Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $wrong->id,
            'status' => 'open',
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $invoiceRight = Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $right->id,
            'status' => 'open',
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ]);

        $payment = Payment::createTrusted([
            'tenant_id' => 1,
            'customer_id' => $wrong->id,
            'amount' => 500,
            'method' => PaymentGateway::BKASH,
            'gateway' => PaymentGateway::BKASH,
            'gateway_transaction_id' => 'TRXTRANSFER1',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::PAYMENT,
            'meta' => ['fifo_multi_invoice' => true],
        ]);

        PaymentProcessor::processCompletedPayment($payment->fresh());

        $wrong->refresh();
        $this->assertGreaterThan(0, (float) $invoiceWrong->fresh()->amount_paid);

        $moved = app(MfsPaymentTransferService::class)->transfer($payment->fresh(), (int) $right->id, 'Wrong auto-match');

        $this->assertSame($right->id, $moved->customer_id);
        $this->assertSame('0782', $moved->customer->customer_code);
        $this->assertSame($wrong->id, $moved->meta['transferred_from_customer_id']);

        $this->assertLessThan(0.02, (float) $invoiceWrong->fresh()->amount_paid);
        $this->assertGreaterThan(0, (float) $invoiceRight->fresh()->amount_paid);
    }
}
