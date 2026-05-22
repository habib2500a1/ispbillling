<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\StaffCollectionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceListCollectPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_bill_list_can_mark_invoice_paid(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $package = Package::query()->create([
            'tenant_id' => 1,
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
            'tenant_id' => 1,
            'customer_code' => 'RC9001',
            'name' => 'Due Client',
            'phone' => '01790000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($user);

        $this->assertTrue(InvoiceResource::canCollectPaymentOnInvoice($invoice->fresh()));

        app(StaffCollectionPaymentService::class)->record($user, $customer, [
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'desk-1',
            'notes' => 'Full payment from bill list',
            'discount_preset' => 'none',
        ], 'admin-invoice-list');

        $invoice->refresh();

        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->where('status', 'completed')->count());
        $this->assertEquals(500.0, (float) $invoice->amount_paid);
        $this->assertSame('paid', $invoice->status);
    }
}
