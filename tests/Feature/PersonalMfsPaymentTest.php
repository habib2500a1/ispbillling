<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\Package;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\PublicCheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalMfsPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bkash_personal_checkout_page(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => 'personal',
            'bkash.personal_number' => '01710000001',
            'mfs_personal.sms_ingest.enabled' => false,
            'mfs_personal.gateways.bkash.auto_verify' => false,
        ]);

        $customer = $this->makeCustomer();
        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        PublicCheckoutSession::put($orderId, [
            'customer_id' => $customer->id,
            'amount' => '250.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => 'bkash',
        ]);

        $this->get('/mfs/bkash/pay?order='.$orderId)
            ->assertOk()
            ->assertSee('bKash payment')
            ->assertSee('01710000001');
    }

    public function test_sms_ingest_and_auto_verify(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => 'personal',
            'bkash.personal_number' => '01710000001',
            'mfs_personal.sms_ingest.enabled' => true,
            'mfs_personal.sms_ingest.api_key' => 'test-device-key',
            'mfs_personal.sms_ingest.require_sms_approved' => false,
            'mfs_personal.sms_ingest.auto_approve_sms' => true,
            'mfs_personal.gateways.bkash.auto_verify' => false,
        ]);

        $customer = $this->makeCustomer();
        $trx = 'BKASH12345';

        $this->postJson('/api/v1/mfs/sms/ingest', [
            'gateway' => 'bkash',
            'transaction_id' => $trx,
            'amount' => 250,
            'device_name' => 'Office Phone',
            'tenant_id' => 1,
        ], ['X-MFS-Device-Key' => 'test-device-key'])
            ->assertOk()
            ->assertJsonPath('transaction_id', $trx);

        $sms = MfsSmsRecord::query()->first();
        $this->assertNotNull($sms);
        $this->assertSame(MfsSmsRecord::STATUS_APPROVED, $sms->status);

        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        $session = [
            'customer_id' => $customer->id,
            'amount' => '250.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => 'bkash',
        ];

        $result = app(GatewayPaymentVerificationService::class)->submitPersonalConfirmation(
            'bkash',
            $orderId,
            $trx,
            $session,
        );

        $this->assertSame('approved', $result['status']);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'gateway_transaction_id' => $trx,
            'status' => 'completed',
        ]);
        $this->assertSame(MfsSmsRecord::STATUS_USED, $sms->fresh()->status);
    }

    public function test_wrong_trx_stays_pending_and_shows_merchant_on_checkout(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => 'personal',
            'bkash.personal_number' => '01711112233',
            'mfs_personal.sms_ingest.enabled' => false,
            'mfs_personal.gateways.bkash.auto_verify' => false,
        ]);

        $customer = $this->makeCustomer();
        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        PublicCheckoutSession::put($orderId, [
            'customer_id' => $customer->id,
            'amount' => '250.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => 'bkash',
        ]);

        $this->post('/mfs/bkash/confirm', [
            'order' => $orderId,
            'transaction_id' => 'WRONGTRX99',
        ])
            ->assertRedirect('/mfs/bkash/pay?order='.$orderId)
            ->assertSessionHas('mfs_pending', true);

        $this->assertDatabaseHas('pending_gateway_payments', [
            'customer_id' => $customer->id,
            'transaction_id' => 'WRONGTRX99',
            'status' => 'pending',
        ]);

        $this->get('/mfs/bkash/pay?order='.$orderId)
            ->assertOk()
            ->assertSee('01711112233')
            ->assertSee('কল করুন');
    }

    public function test_pending_auto_approves_when_sms_arrives_late(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => 'personal',
            'bkash.personal_number' => '01710000001',
            'mfs_personal.sms_ingest.enabled' => true,
            'mfs_personal.sms_ingest.api_key' => 'test-device-key',
            'mfs_personal.sms_ingest.require_sms_approved' => false,
            'mfs_personal.sms_ingest.auto_approve_sms' => true,
            'mfs_personal.gateways.bkash.auto_verify' => false,
        ]);

        $customer = $this->makeCustomer();
        $trx = 'LATEBKASH99';
        $orderId = PublicCheckoutSession::makeTranId($customer->id, null);
        $session = [
            'customer_id' => $customer->id,
            'amount' => '250.00',
            'return_to' => 'bill_payment',
            'payment_type' => 'payment',
            'gateway' => 'bkash',
        ];

        $pending = app(GatewayPaymentVerificationService::class)->submitPersonalConfirmation(
            'bkash',
            $orderId,
            $trx,
            $session,
        );
        $this->assertSame('pending', $pending['status']);

        $this->postJson('/api/v1/mfs/sms/ingest', [
            'gateway' => 'bkash',
            'transaction_id' => $trx,
            'amount' => 250,
            'device_name' => 'Office Phone',
            'tenant_id' => 1,
        ], ['X-MFS-Device-Key' => 'test-device-key'])
            ->assertOk()
            ->assertJsonPath('matched_pending', 1);

        $this->assertDatabaseHas('pending_gateway_payments', [
            'transaction_id' => $trx,
            'status' => 'auto_approved',
        ]);
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'gateway_transaction_id' => $trx,
            'status' => 'completed',
        ]);
    }

    public function test_sms_auto_approves_by_subscriber_id_with_leading_zero(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => 'personal',
            'mfs_personal.sms_ingest.enabled' => true,
            'mfs_personal.sms_ingest.api_key' => 'test-device-key',
            'mfs_personal.sms_ingest.auto_approve_sms' => true,
            'mfs_personal.sms_ingest.auto_approve_by_reference' => true,
            'mfs_personal.gateways.bkash.auto_verify' => true,
        ]);

        $customer = $this->makeCustomer('790');

        $trx = 'REFIDBKASH01';
        $raw = 'You have received Tk 250.00 from 01712345678 Ref 790 Fee Tk 0.00. TrxID '.$trx;

        $this->postJson('/api/v1/mfs/sms/ingest', [
            'gateway' => 'bkash',
            'transaction_id' => $trx,
            'amount' => 250,
            'raw_message' => $raw,
            'customer_reference' => '790',
            'tenant_id' => 1,
        ], ['X-MFS-Device-Key' => 'test-device-key'])
            ->assertOk()
            ->assertJsonPath('auto_approved', true)
            ->assertJsonPath('matched_customer_id', $customer->id)
            ->assertJsonPath('reference_match', 'auto_approved')
            ->assertJsonPath('reference_token', '790');

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'gateway_transaction_id' => $trx,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('pending_gateway_payments', [
            'customer_id' => $customer->id,
            'transaction_id' => $trx,
            'status' => 'auto_approved',
        ]);
    }

    public function test_duplicate_trx_rejected(): void
    {
        config([
            'mfs_personal.gateways.bkash.auto_verify' => false,
        ]);

        $customer = $this->makeCustomer();
        \App\Models\PendingGatewayPayment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'gateway' => 'bkash',
            'transaction_id' => 'DUP123',
            'amount' => 100,
            'status' => \App\Models\PendingGatewayPayment::STATUS_PENDING,
        ]);

        $result = app(GatewayPaymentVerificationService::class)->submitPersonalConfirmation(
            'bkash',
            'order-1',
            'DUP123',
            ['customer_id' => $customer->id, 'amount' => '100.00'],
        );

        $this->assertSame('duplicate', $result['status']);
    }

    private function makeCustomer(?string $customerCode = null): Customer
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

        return Customer::query()->create([
            'name' => 'MFS User',
            'customer_code' => $customerCode ?? ('C'.fake()->unique()->numerify('####')),
            'phone' => '017'.fake()->unique()->numerify('########'),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }
}
