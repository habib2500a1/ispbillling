<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Services\Payments\MfsSmsAutoApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MfsSmsRefOverridesPortalCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_ref_overrides_wrong_portal_pending_customer(): void
    {
        config([
            'mfs_personal.sms_ingest.enabled' => true,
            'mfs_personal.sms_ingest.auto_approve_by_reference' => true,
            'mfs_personal.gateways.bkash.auto_verify' => true,
        ]);

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
            'name' => 'Habib Portal',
            'customer_code' => 'habibfree',
            'phone' => '01841558023',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $right = Customer::query()->create([
            'name' => 'Fariya',
            'customer_code' => '0782',
            'phone' => '01339078960',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $trx = 'DEN5HXLU09';

        $sms = MfsSmsRecord::query()->create([
            'tenant_id' => 1,
            'gateway' => 'bkash',
            'sender_type' => 'personal',
            'transaction_id' => $trx,
            'amount' => 500,
            'status' => MfsSmsRecord::STATUS_APPROVED,
            'sender_phone' => '01841558023',
            'raw_message' => 'You have received payment Tk 500.00 from 01841558023. Ref 782. TrxID '.$trx,
            'sms_received_at' => now(),
        ]);

        $pending = PendingGatewayPayment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $wrong->id,
            'gateway' => 'bkash',
            'transaction_id' => $trx,
            'amount' => 500,
            'status' => PendingGatewayPayment::STATUS_PENDING,
            'checkout_order_id' => 'portal-order-1',
            'meta' => [],
        ]);

        $count = app(MfsSmsAutoApprovalService::class)->processApprovedSms($sms->fresh());
        $this->assertSame(1, $count);

        $pending->refresh();
        $this->assertSame(PendingGatewayPayment::STATUS_AUTO_APPROVED, $pending->status);
        $this->assertSame($right->id, $pending->customer_id);
        $this->assertTrue($pending->meta['sms_reference_override'] ?? false);

        $payment = Payment::query()->find($pending->payment_id);
        $this->assertNotNull($payment);
        $this->assertSame($right->id, $payment->customer_id);
        $this->assertSame('782', $pending->meta['reference_token'] ?? null);
    }
}
