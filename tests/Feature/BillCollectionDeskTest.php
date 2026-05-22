<?php

namespace Tests\Feature;

use App\Filament\Pages\BillCollectionDesk;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Billing\PaymentAllocationCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillCollectionDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_customer_by_code_phone_username_and_address(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Test ISP', 'slug' => 'test-isp', 'is_active' => true]);
        $area = Area::query()->create(['tenant_id' => $tenant->id, 'name' => 'Mirpur', 'code' => 'MIR', 'is_active' => true]);

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'customer_code' => 'RC1001',
            'name' => 'Karim Ahmed',
            'phone' => '01711112222',
            'mikrotik_secret_name' => 'karim_ppp',
            'address' => 'House 12, Road 5',
            'area_id' => $area->id,
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $service = app(BillCollectionSearchService::class);

        $contains = fn (string $term): bool => $service->search($term)->contains(
            fn (array $row): bool => $row['id'] === $customer->id
        );

        $this->assertTrue($contains('RC1001'));
        $this->assertTrue($contains('01711112222'));
        $this->assertTrue($contains('karim_ppp'));
        $this->assertTrue($contains('Mirpur'));

        $row = $service->find($customer->id);
        $this->assertSame('RC1001', $row['customer_code']);
        $this->assertSame('karim_ppp', $row['username']);
        $this->assertStringContainsString('Mirpur', $row['address']);
    }

    public function test_admin_can_open_bill_collection_desk(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(BillCollectionDesk::getUrl())
            ->assertOk()
            ->assertSee('Bill collection desk', false)
            ->assertSee('Find subscriber', false);
    }

    public function test_collect_payment_creates_completed_payment(): void
    {
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
            'name' => 'Pay Test',
            'phone' => '01730000099',
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
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(BillCollectionDesk::class)
            ->set('search', $customer->customer_code)
            ->call('runSearch')
            ->call('selectCustomer', $customer->id)
            ->set('invoiceId', $invoice->id)
            ->set('amount', '500')
            ->set('method', 'cash')
            ->call('collectPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'status' => 'completed',
            'method' => 'cash',
        ]);
    }

    public function test_full_payment_without_invoice_id_clears_due_in_ui(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Auto Invoice Pay',
            'phone' => '01730000054',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 400,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 400,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(BillCollectionDesk::class)
            ->call('selectCustomer', $customer->id)
            ->set('amount', '400')
            ->set('method', 'cash')
            ->call('collectPayment')
            ->assertHasNoErrors()
            ->assertSet('selectedCustomer.balance_due', 0.0);

        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_partial_payment_leaves_invoice_balance_and_stores_notes(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Partial Pay',
            'phone' => '01730000055',
            'status' => 'active',
            'billing_day' => 1,
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

        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(BillCollectionDesk::class)
            ->call('selectCustomer', $customer->id)
            ->set('invoiceId', $invoice->id)
            ->set('amount', '200')
            ->set('method', 'cash')
            ->set('notes', '500 এর মধ্যে 200 নিলাম, বাকি 300 পরে')
            ->call('collectPayment')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('partial', $invoice->status);
        $this->assertSame(200.0, (float) $invoice->amount_paid);
        $this->assertSame(300.0, $invoice->balanceDue());
        $this->assertDatabaseHas('payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'notes' => '500 এর মধ্যে 200 নিলাম, বাকি 300 পরে',
            'status' => 'completed',
        ]);
    }

    public function test_customer_ledger_includes_bill_and_collection_history(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Ledger Test',
            'phone' => '01730000088',
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
            'discount_amount' => 0,
            'total' => 300,
            'amount_paid' => 100,
            'status' => 'partial',
        ]);

        $row = app(BillCollectionSearchService::class)->find($customer->id);

        $this->assertNotNull($row);
        $this->assertCount(1, $row['bill_history']);
        $this->assertSame($invoice->invoice_number, $row['bill_history'][0]['invoice_number']);
        $this->assertSame(200.0, $row['bill_history'][0]['balance_due']);
    }

    public function test_payment_can_be_reassigned_to_correct_invoice(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Reassign Test',
            'phone' => '01730000077',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $wrongInvoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 200,
            'amount_paid' => 200,
            'status' => 'paid',
        ]);

        $correctInvoice = Invoice::query()->create([
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

        $payment = \App\Models\Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $wrongInvoice->id,
            'amount' => 200,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'meta' => ['processed' => true, 'invoice_applied' => 200],
        ]);

        app(PaymentAllocationCorrectionService::class)->reassign(
            $payment,
            $correctInvoice->id,
            200,
        );

        $wrongInvoice->refresh();
        $correctInvoice->refresh();

        $this->assertSame(200.0, $wrongInvoice->balanceDue());
        $this->assertSame('open', $wrongInvoice->status);
        $this->assertSame(300.0, $correctInvoice->balanceDue());
        $this->assertSame('partial', $correctInvoice->status);
        $this->assertSame($correctInvoice->id, $payment->fresh()->invoice_id);
    }
}
