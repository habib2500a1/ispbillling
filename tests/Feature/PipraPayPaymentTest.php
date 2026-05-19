<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PipraPayPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_piprapay_success_records_payment(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-api-key',
            'piprapay.api_mode' => 'redirect',
            'piprapay.base_url' => 'https://pay.flixbd.xyz/api',
            'accounting.auto_post_customer_payments' => false,
        ]);

        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Pipra Customer',
            'phone' => '01730000044',
            'email' => 'pipra@test.local',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $orderId = PublicCheckoutSession::makeTranId($customer->id, $invoice->id);
        PublicCheckoutSession::put($orderId, [
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => '500.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => PaymentGateway::PIPRAPAY,
        ]);

        Http::fake([
            'pay.flixbd.xyz/api/verify-payment' => Http::response([
                'status' => 'completed',
                'amount' => 500,
                'metadata' => json_encode(['order_id' => $orderId]),
            ]),
        ]);

        $this->get(route('piprapay.success', ['pp_id' => 'PP-TEST-001', 'order_id' => $orderId]))
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGateway::PIPRAPAY,
            'gateway_transaction_id' => 'PP-TEST-001',
            'amount' => 500,
            'status' => 'completed',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_piprapay_success_recovers_when_cache_session_expired(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-api-key',
            'piprapay.api_mode' => 'redirect',
            'piprapay.base_url' => 'https://pay.flixbd.xyz/api',
            'accounting.auto_post_customer_payments' => false,
        ]);

        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Pipra Cache Customer',
            'phone' => '01730000055',
            'email' => 'pipra-cache@test.local',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $orderId = PublicCheckoutSession::makeTranId($customer->id, $invoice->id);
        $session = [
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => '500.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => PaymentGateway::PIPRAPAY,
        ];

        \App\Services\Payments\PipraPayCheckoutStore::persist($orderId, $session);
        // Simulate expired Redis checkout session (only DB row remains).
        PublicCheckoutSession::forget($orderId);

        Http::fake([
            'pay.flixbd.xyz/api/verify-payment' => Http::response([
                'status' => 'completed',
                'amount' => 500,
                'metadata' => json_encode([
                    'order_id' => $orderId,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'payment_type' => 'payment',
                ]),
            ]),
        ]);

        $this->get(route('piprapay.success', ['pp_id' => 'PP-CACHE-001']))
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'gateway_transaction_id' => 'PP-CACHE-001',
            'status' => 'completed',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    public function test_piprapay_wallet_topup_credits_balance_when_session_expired(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-api-key',
            'piprapay.api_mode' => 'redirect',
            'piprapay.base_url' => 'https://pay.flixbd.xyz/api',
            'accounting.auto_post_customer_payments' => false,
        ]);

        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Wallet Customer',
            'phone' => '01730000066',
            'email' => 'wallet-pipra@test.local',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'account_balance' => 0,
        ]);

        $orderId = PublicCheckoutSession::makeTranId($customer->id);
        $session = [
            'customer_id' => $customer->id,
            'amount' => '200.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'wallet_deposit',
            'gateway' => PaymentGateway::PIPRAPAY,
        ];

        \App\Services\Payments\PipraPayCheckoutStore::persist($orderId, $session);

        Http::fake([
            'pay.flixbd.xyz/api/verify-payment' => Http::response([
                'status' => 'completed',
                'amount' => 200,
                'metadata' => json_encode([
                    'order_id' => $orderId,
                    'customer_id' => $customer->id,
                    'payment_type' => 'wallet_deposit',
                ]),
            ]),
        ]);

        $this->get(route('piprapay.success', ['pp_id' => 'PP-WALLET-001']))
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame(200.0, (float) $customer->account_balance);
    }

    public function test_piprapay_webhook_is_not_blocked_by_csrf(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'secret-key',
        ]);

        $this->postJson(route('piprapay.webhook'), ['pp_id' => 'PP-X'], [
            'MHS-PIPRAPAY-API-KEY' => 'wrong',
        ])->assertUnauthorized();

        $this->assertNotEquals(419, $this->postJson(route('piprapay.webhook'), ['pp_id' => 'PP-X'])->status());
    }

    public function test_piprapay_success_shows_pending_message_when_awaiting_approval(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-api-key',
            'piprapay.api_mode' => 'redirect',
            'piprapay.base_url' => 'https://pay.flixbd.xyz/api',
        ]);

        Http::fake([
            'pay.flixbd.xyz/api/verify-payment' => Http::response([
                'status' => 'pending',
                'amount' => 100,
            ]),
        ]);

        $this->get(route('piprapay.success', ['pp_id' => 'PP-PENDING-001']))
            ->assertRedirect(route('bill-payment.index'))
            ->assertSessionHas('status');
    }

    public function test_piprapay_sync_pending_records_completed_payment(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-api-key',
            'piprapay.api_mode' => 'redirect',
            'piprapay.base_url' => 'https://pay.flixbd.xyz/api',
            'accounting.auto_post_customer_payments' => false,
        ]);

        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Sync Customer',
            'phone' => '01730000077',
            'email' => 'sync@test.local',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $orderId = PublicCheckoutSession::makeTranId($customer->id);
        \App\Services\Payments\PipraPayCheckoutStore::persist($orderId, [
            'customer_id' => $customer->id,
            'amount' => '150.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'wallet_deposit',
            'gateway' => PaymentGateway::PIPRAPAY,
        ], 'PP-SYNC-001');

        Http::fake([
            'pay.flixbd.xyz/api/verify-payment' => Http::response([
                'status' => 'completed',
                'amount' => 150,
                'metadata' => json_encode([
                    'order_id' => $orderId,
                    'customer_id' => $customer->id,
                    'payment_type' => 'wallet_deposit',
                ]),
            ]),
        ]);

        $this->artisan('piprapay:sync-pending')->assertSuccessful();

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'gateway_transaction_id' => 'PP-SYNC-001',
            'status' => 'completed',
        ]);
    }

    public function test_piprapay_webhook_rejects_invalid_api_key(): void
    {
        config([
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'secret-key',
        ]);

        $this->postJson(route('piprapay.webhook'), ['pp_id' => 'PP-X'], [
            'MHS-PIPRAPAY-API-KEY' => 'wrong',
        ])->assertUnauthorized();
    }
}
