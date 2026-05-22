<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CustomerBalanceDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBalanceDueTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_zeroes_due_despite_stale_isp_meta(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'DUE'.random_int(1000, 9999),
            'name' => 'Due Test',
            'phone' => '01700000002',
            'status' => 'active',
            'meta' => [
                'isp_digital_billing_synced_at' => now()->toIso8601String(),
                'isp_digital_balance_due' => 500,
                'isp_digital_payment_state' => 'unpaid',
            ],
        ]);

        Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'ISD-'.$customer->customer_code.'-'.now()->format('Y-m'),
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(10),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'subtotal' => 500,
            'total' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);

        $this->assertSame(0.0, CustomerBalanceDue::amount($customer->fresh()));
    }

    public function test_partial_invoice_due_used_when_lower_than_stale_isp(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'DUE'.random_int(1000, 9999),
            'name' => 'Partial Due',
            'phone' => '01700000003',
            'status' => 'active',
            'meta' => [
                'isp_digital_billing_synced_at' => now()->toIso8601String(),
                'isp_digital_balance_due' => 500,
            ],
        ]);

        Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'ISD-'.$customer->customer_code.'-PART',
            'issue_date' => now(),
            'due_date' => now()->addDays(5),
            'period_start' => now(),
            'period_end' => now(),
            'subtotal' => 500,
            'total' => 500,
            'amount_paid' => 300,
            'status' => 'partial',
        ]);

        $this->assertSame(200.0, CustomerBalanceDue::amount($customer->fresh()));
    }

    public function test_augment_table_query_keeps_customer_primary_key(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'DUE'.random_int(1000, 9999),
            'name' => 'Table Key',
            'phone' => '01700000005',
            'status' => 'active',
        ]);

        $loaded = CustomerBalanceDue::augmentTableQuery(Customer::query())
            ->whereKey($customer->id)
            ->first();

        $this->assertNotNull($loaded);
        $this->assertSame($customer->id, $loaded->id);
        $this->assertArrayHasKey('name', $loaded->getAttributes());
    }

    public function test_refresh_meta_removes_legacy_isp_due_and_sets_invoice_due(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'DUE'.random_int(1000, 9999),
            'name' => 'Meta Sync',
            'phone' => '01700000004',
            'status' => 'active',
            'meta' => [
                'isp_digital_billing_synced_at' => now()->toIso8601String(),
                'isp_digital_balance_due' => 500,
                'isp_digital_payment_state' => 'unpaid',
            ],
        ]);

        Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'invoice_number' => 'ISD-'.$customer->customer_code.'-'.now()->format('Y-m'),
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(10),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'subtotal' => 500,
            'total' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);

        CustomerBalanceDue::refreshMetaAfterPayment($customer->fresh());

        $meta = $customer->fresh()->meta;
        $this->assertArrayNotHasKey('isp_digital_balance_due', $meta);
        $this->assertSame(0.0, (float) ($meta['balance_due'] ?? -1));
        $this->assertSame('paid', $meta['billing_payment_state'] ?? '');
    }
}
