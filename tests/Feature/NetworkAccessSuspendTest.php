<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkAccessSuspendTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_suspends_on_overdue_balance_when_auto_suspend_enabled(): void
    {
        config(['network.auto_suspend_enabled' => true]);

        $package = Package::query()->create([
            'name' => 'Net Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Net Customer',
            'phone' => '01600000099',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->subDays(20)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        app(NetworkAccessCoordinator::class)->syncCustomer($customer->fresh());

        $this->assertSame('suspended', $customer->fresh()->network_access_state);
    }

    public function test_completed_full_payment_restores_network_access_state(): void
    {
        config(['network.auto_suspend_enabled' => true]);

        $package = Package::query()->create([
            'name' => 'Net Plan B',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Net Customer B',
            'phone' => '01600000098',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'suspended',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'period_start' => now()->subDays(20)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->app->terminate();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $customer->fresh()->network_access_state);
    }
}
