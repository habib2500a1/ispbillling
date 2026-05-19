<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\CashbookService;
use App\Services\Accounting\ChartOfAccountSeeder;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PayrollService;
use App\Services\Accounting\VendorPaymentService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
        app(ChartOfAccountSeeder::class)->seedForTenant(1);
    }

    public function test_ledger_posts_balanced_journal(): void
    {
        $entry = app(LedgerService::class)->post('Test entry', [
            ['account_code' => '1000', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ]);

        $this->assertTrue($entry->isBalanced());
        $this->assertEquals('posted', $entry->status);
        $this->assertCount(2, $entry->lines);
    }

    public function test_cashbook_creates_journal(): void
    {
        $cb = app(CashbookService::class)->record('in', 250, 'Walk-in customer', '4100');

        $this->assertDatabaseHas('cashbook_entries', ['id' => $cb->id, 'direction' => 'in']);
        $this->assertNotNull($cb->journal_entry_id);
    }

    public function test_customer_payment_auto_posts_to_ledger(): void
    {
        $package = Package::query()->create([
            'name' => 'Acct Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Acct Cust',
            'phone' => '01710002233',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 300,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'payment',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'customer_payment',
        ]);
    }

    public function test_vendor_payment_posts_expense(): void
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => 1,
            'name' => 'Fiber supplier',
            'is_active' => true,
        ]);

        $payment = VendorPayment::query()->create([
            'tenant_id' => 1,
            'vendor_id' => $vendor->id,
            'payment_date' => now(),
            'amount' => 1000,
            'vat_amount' => 150,
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        app(VendorPaymentService::class)->recordPayment($payment);

        $payment->refresh();
        $this->assertNotNull($payment->journal_entry_id);
    }

    public function test_payroll_generates_and_pays(): void
    {
        Employee::query()->create([
            'tenant_id' => 1,
            'name' => 'Tech',
            'base_salary' => 20000,
            'is_active' => true,
        ]);

        $run = app(PayrollService::class)->generateDraft((int) now()->month, (int) now()->year);
        $this->assertEquals('draft', $run->status);
        $this->assertGreaterThan(0, (float) $run->total_net);

        $paid = app(PayrollService::class)->markPaid($run);
        $this->assertEquals('paid', $paid->status);
        $this->assertNotNull($paid->journal_entry_id);
    }

    public function test_profit_and_loss_report(): void
    {
        app(LedgerService::class)->post('Revenue', [
            ['account_code' => '1000', 'debit' => 1000],
            ['account_code' => '4000', 'credit' => 1000],
        ]);
        app(LedgerService::class)->post('Expense', [
            ['account_code' => '5200', 'debit' => 200],
            ['account_code' => '1000', 'credit' => 200],
        ]);

        $pl = app(AccountingReportService::class)->profitAndLoss(now()->startOfMonth(), now()->endOfMonth());

        $this->assertEqualsWithDelta(1000.0, $pl['income'], 0.01);
        $this->assertEqualsWithDelta(200.0, $pl['expenses'], 0.01);
        $this->assertEqualsWithDelta(800.0, $pl['net_profit'], 0.01);
    }

    public function test_chart_seeder_creates_accounts(): void
    {
        $this->assertGreaterThanOrEqual(10, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', 1)->count());
    }
}
