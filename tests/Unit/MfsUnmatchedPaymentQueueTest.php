<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\Package;
use App\Models\PendingGatewayPayment;
use App\Models\User;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\MfsSmsAutoApprovalService;
use App\Services\Payments\MfsUnmatchedPaymentQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfsUnmatchedPaymentQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_unmatched_sms_creates_pending_without_customer(): void
    {
        config(['mfs_personal.sms_ingest.auto_approve_by_reference' => true]);

        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        Customer::query()->create([
            'name' => 'Other',
            'customer_code' => '9901',
            'phone' => '01700000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $sms = MfsSmsRecord::query()->create([
            'tenant_id' => 1,
            'gateway' => 'bkash',
            'sender_type' => 'personal',
            'transaction_id' => 'TRXUNMATCH1',
            'amount' => 500,
            'status' => MfsSmsRecord::STATUS_APPROVED,
            'raw_message' => 'You have received Tk 500 from 01700000000. Ref 99999999. TrxID TRXUNMATCH1',
            'sms_received_at' => now(),
        ]);

        $count = app(MfsSmsAutoApprovalService::class)->approveByReference($sms->fresh());
        $this->assertSame(0, $count);

        $pending = PendingGatewayPayment::query()
            ->where('transaction_id', 'TRXUNMATCH1')
            ->first();

        $this->assertNotNull($pending);
        $this->assertNull($pending->customer_id);
        $this->assertTrue($pending->needsCustomerAssignment());
        $this->assertSame('needs_assignment', $pending->meta['reference_match']);
    }

    public function test_admin_assign_and_approve_applies_payment(): void
    {
        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Assign Me',
            'customer_code' => '8801',
            'phone' => '01700000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $pending = app(MfsUnmatchedPaymentQueue::class)->queueFromSms(
            MfsSmsRecord::query()->create([
                'tenant_id' => 1,
                'gateway' => 'bkash',
                'sender_type' => 'personal',
                'transaction_id' => 'TRXASSIGN1',
                'amount' => 200,
                'status' => MfsSmsRecord::STATUS_APPROVED,
                'raw_message' => 'TrxID TRXASSIGN1',
                'sms_received_at' => now(),
            ]),
            ['customer' => null, 'customers' => [], 'token' => '8801', 'matched_by' => null, 'candidates' => ['8801']],
        );

        $reviewer = User::factory()->create(['tenant_id' => 1]);

        $payment = app(GatewayPaymentVerificationService::class)->assignAndApprove(
            $pending,
            (int) $customer->id,
            null,
            (int) $reviewer->id,
        );

        $this->assertSame($customer->id, $payment->customer_id);
        $pending->refresh();
        $this->assertSame(PendingGatewayPayment::STATUS_APPROVED, $pending->status);
        $this->assertNotNull($pending->payment_id);
    }
}
