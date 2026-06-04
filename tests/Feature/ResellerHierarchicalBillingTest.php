<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerLedgerEntry;
use App\Models\ResellerPackage;
use App\Services\Resellers\ResellerDueLedgerService;
use App\Services\Resellers\ResellerHierarchicalBillingService;
use App\Services\Resellers\ResellerInvoiceSplitCalculator;
use App\Support\ResellerBillingSettlementMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerHierarchicalBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_postpaid_invoice_accrues_admin_receivable_not_wallet(): void
    {
        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Reseller Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Partner',
            'code' => 'RSL-HIER-001',
            'password' => 'secret',
            'billing_settlement_mode' => ResellerBillingSettlementMode::POSTPAID_DUE,
            'credit_limit' => 50000,
            'wallet_balance' => 10000,
        ]);
        ResellerPackage::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'package_id' => $package->id,
            'wholesale_price' => 800,
            'selling_price' => 1000,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Sub',
            'phone' => '01721111111',
            'status' => 'active',
            'billing_day' => 1,
            'reseller_id' => $reseller->id,
            'package_id' => $package->id,
        ]);
        $invoice = Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1000,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        app(ResellerHierarchicalBillingService::class)->handleInvoiceCreated($invoice);

        $reseller->refresh();
        $this->assertGreaterThan(0, (float) $reseller->admin_receivable_due);
        $this->assertDatabaseHas('reseller_ledger_entries', [
            'reseller_id' => $reseller->id,
            'entry_type' => ResellerLedgerEntry::TYPE_ADMIN_RECEIVABLE_ACCRUAL,
        ]);

        $split = app(ResellerInvoiceSplitCalculator::class)->splitForInvoice($invoice->fresh(['customer.package', 'customer.reseller', 'items']));
        $this->assertEquals(1000.0, $split['retail']);
        $this->assertGreaterThan(0, $split['wholesale']);
        $this->assertEquals(max(0, $split['retail'] - $split['wholesale']), $split['margin']);
    }

    public function test_settlement_reduces_admin_due(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Settle Partner',
            'code' => 'RSL-HIER-002',
            'password' => 'secret',
            'admin_receivable_due' => 5000,
            'billing_settlement_mode' => ResellerBillingSettlementMode::POSTPAID_DUE,
        ]);

        app(ResellerDueLedgerService::class)->recordSettlement($reseller, 2000);

        $reseller->refresh();
        $this->assertEquals(3000.0, (float) $reseller->admin_receivable_due);
    }
}
