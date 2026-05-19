<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => 'ISP '.$slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createCustomer(Tenant $tenant, string $code): Customer
    {
        return Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => $code,
            'name' => 'Test '.$code,
            'phone' => '017'.substr(md5($code), 0, 8),
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
        ]);
    }

    public function test_payment_rejects_tenant_id_via_mass_assignment(): void
    {
        $tenant = $this->createTenant('ma-pay-a');
        $otherTenant = $this->createTenant('ma-pay-b');
        $customer = $this->createCustomer($tenant, 'C-MA-001');

        $payment = Payment::query()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $customer->id,
            'amount' => 100,
            'method' => 'cash',
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'paid_at' => now(),
        ]);

        $this->assertSame($customer->tenant_id, $payment->tenant_id);
        $this->assertNotSame($otherTenant->id, $payment->tenant_id);
    }

    public function test_create_trusted_sets_guarded_payment_fields(): void
    {
        $tenant = $this->createTenant('ma-pay-c');
        $customer = $this->createCustomer($tenant, 'C-MA-002');

        $payment = Payment::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'amount' => 50,
            'method' => 'cash',
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'paid_at' => now(),
            'receipt_number' => 'RCP-TEST-00001',
        ]);

        $this->assertSame('RCP-TEST-00001', $payment->receipt_number);
    }

    public function test_customer_rejects_guarded_import_fields_via_mass_assignment(): void
    {
        $tenant = $this->createTenant('ma-cust-a');

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_code' => 'C-MA-003',
            'name' => 'Guarded Import',
            'phone' => '01700000001',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'import_source' => 'mikrotik',
            'mikrotik_synced_at' => now(),
        ]);

        $this->assertNull($customer->import_source);
        $this->assertNull($customer->mikrotik_synced_at);
    }

    public function test_create_trusted_sets_guarded_customer_fields(): void
    {
        $tenant = $this->createTenant('ma-cust-b');
        $syncedAt = now()->startOfSecond();

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'C-MA-004',
            'name' => 'Trusted Import',
            'phone' => '01700000002',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'import_source' => 'mikrotik',
            'mikrotik_synced_at' => $syncedAt,
        ]);

        $this->assertSame('mikrotik', $customer->import_source);
        $this->assertTrue($customer->mikrotik_synced_at->equalTo($syncedAt));
    }

    public function test_invoice_number_not_mass_assignable(): void
    {
        $tenant = $this->createTenant('ma-inv-a');
        $customer = $this->createCustomer($tenant, 'C-MA-005');

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
            'invoice_number' => 'INV-FORGED-001',
        ]);

        $this->assertNotSame('INV-FORGED-001', $invoice->invoice_number);
        $this->assertNotEmpty($invoice->invoice_number);
    }
}
