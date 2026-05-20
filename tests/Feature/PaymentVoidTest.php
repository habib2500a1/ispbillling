<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentVoidService;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentVoidTest extends TestCase
{
    use RefreshDatabase;

    public function test_void_restores_invoice_and_wallet_after_overpayment(): void
    {
        config(['payments.overpayment_to_wallet' => true]);

        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Void Test',
            'phone' => '01731111111',
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
            'subtotal' => 500,
            'tax_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 600,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::PAYMENT,
        ]);

        $invoice->refresh();
        $customer->refresh();
        $this->assertSame(500.0, (float) $invoice->amount_paid);
        $this->assertSame(100.0, (float) $customer->account_balance);

        app(PaymentVoidService::class)->void($payment, 'Wrong duplicate entry');

        $payment->refresh();
        $invoice->refresh();
        $customer->refresh();

        $this->assertSame('void', $payment->status);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame('open', $invoice->status);
        $this->assertSame(0.0, (float) $customer->account_balance);
    }

    public function test_admin_can_void_via_api(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user, ['staff']);

        $customer = Customer::query()->create([
            'name' => 'API Void',
            'phone' => '01732222222',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 300,
            'tax_amount' => 0,
            'total' => 300,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::PAYMENT,
        ]);

        $this->deleteJson("/api/v1/staff/payments/{$payment->id}", ['reason' => 'Wrong customer'])
            ->assertOk()
            ->assertJsonPath('payment.status', 'void');

        $this->assertSame(0.0, (float) $invoice->fresh()->amount_paid);
    }
}
