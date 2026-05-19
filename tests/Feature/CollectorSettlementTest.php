<?php

namespace Tests\Feature;

use App\Models\CollectorCollection;
use App\Models\CollectorSettlement;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Models\CollectorExpenseCategory;
use App\Services\Collector\CollectorSettlementService;
use App\Services\Collector\CollectorWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectorSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_attribute_collection_to_collector_staff(): void
    {
        config(['collector.enabled' => true, 'accounting.auto_post_customer_payments' => false]);

        Role::findOrCreate('cashier', 'web');
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create(['tenant_id' => 1, 'name' => 'Main Admin']);
        $admin->assignRole('admin');

        $collector = User::factory()->create(['tenant_id' => 1, 'name' => 'Field Collector']);
        $collector->assignRole('cashier');

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Attrib Test',
            'phone' => '01710009988',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
            'meta' => [
                'entered_by' => $admin->id,
                'entered_by_name' => $admin->name,
            ],
        ]);

        $collection = app(CollectorSettlementService::class)->recordCollectionFromPayment($payment);

        $this->assertNotNull($collection);
        $this->assertSame($collector->id, $collection->collector_id);
        $this->assertSame(500.0, (float) $collection->amount);

        $balance = app(CollectorSettlementService::class)->balanceForCollector($collector->id);
        $this->assertSame(500.0, $balance['outstanding']);
    }

    public function test_cash_payment_creates_collector_collection(): void
    {
        config(['collector.enabled' => true, 'accounting.auto_post_customer_payments' => false]);

        $collector = User::factory()->create(['tenant_id' => 1]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Collector Test',
            'phone' => '01710001122',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 1000,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
        ]);

        $collection = app(CollectorSettlementService::class)->recordCollectionFromPayment($payment);

        $this->assertNotNull($collection);
        $this->assertSame(1000.0, (float) $collection->amount);
        $this->assertSame('open', $collection->status);

        $balance = app(CollectorSettlementService::class)->balanceForCollector($collector->id);
        $this->assertSame(1000.0, $balance['outstanding']);
    }

    public function test_partial_settlement_reduces_outstanding(): void
    {
        config([
            'collector.enabled' => true,
            'collector.settlement_requires_approval' => false,
            'accounting.auto_post_customer_payments' => false,
        ]);

        $collector = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('isp-admin');

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'A',
            'phone' => '01710001123',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 1000,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
        ]);

        $this->actingAs($collector);

        app(CollectorSettlementService::class)->submitSettlement(
            collectorId: $collector->id,
            amount: 500,
        );

        $balance = app(CollectorSettlementService::class)->balanceForCollector($collector->id);
        $this->assertSame(500.0, $balance['outstanding']);
        $this->assertDatabaseHas('collector_settlements', [
            'collector_id' => $collector->id,
            'amount' => 500,
            'status' => 'approved',
        ]);
    }

    public function test_approved_expense_reduces_cash_in_hand(): void
    {
        config([
            'collector.enabled' => true,
            'collector.expense_requires_approval' => false,
            'accounting.auto_post_customer_payments' => false,
        ]);

        $collector = User::factory()->create(['tenant_id' => 1]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Expense Test',
            'phone' => '01710001124',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 10000,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
        ]);

        app(CollectorWalletService::class)->ensureDefaultCategories(1);
        $category = CollectorExpenseCategory::query()->where('code', 'fuel')->firstOrFail();

        app(CollectorWalletService::class)->submitExpense(
            collectorId: $collector->id,
            amount: 1000,
            categoryId: $category->id,
            submittedBy: $collector->id,
        );

        $wallet = app(CollectorWalletService::class)->wallet($collector->id);
        $this->assertSame(9000.0, $wallet['cash_in_hand']);
    }

    public function test_admin_can_receive_partial_cash_from_staff(): void
    {
        config([
            'collector.enabled' => true,
            'collector.settlement_requires_approval' => true,
            'accounting.auto_post_customer_payments' => false,
        ]);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('cashier', 'web');

        $admin = User::factory()->create(['tenant_id' => 1, 'name' => 'Owner']);
        $admin->assignRole('admin');

        $collector = User::factory()->create(['tenant_id' => 1, 'name' => 'Field Staff']);
        $collector->assignRole('cashier');

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Staff Due Test',
            'phone' => '01710001125',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\CollectorCashHub::class)
            ->set('adminReceiveStaffId', $collector->id)
            ->set('adminReceiveAmount', '200')
            ->set('adminReceiveNotes', '500 এর মধ্যে 200 নিলাম, বাকি 300')
            ->call('receiveCashFromStaff')
            ->assertHasNoErrors();

        $wallet = app(CollectorWalletService::class)->wallet($collector->id);
        $this->assertSame(300.0, $wallet['cash_in_hand']);

        $this->assertDatabaseHas('collector_settlements', [
            'collector_id' => $collector->id,
            'amount' => 200,
            'status' => 'approved',
            'notes' => '500 এর মধ্যে 200 নিলাম, বাকি 300',
        ]);
    }

    public function test_admin_can_open_collector_settlement_page(): void
    {
        config(['collector.enabled' => true]);

        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(\App\Filament\Pages\CollectorCashHub::getUrl())
            ->assertOk()
            ->assertSee('Collection settlement & collector due', false);
    }
}
