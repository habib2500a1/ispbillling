<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateBillsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_create_invoices(): void
    {
        $package = Package::query()->create([
            'name' => 'Bill Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'Bill Customer',
            'phone' => '01500000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        Artisan::call('isp:generate-bills', [
            '--dry-run' => true,
            '--force' => true,
            '--date' => now()->startOfMonth()->addDays(0)->toDateString(),
        ]);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_generates_invoice_on_first_of_month_when_billing_day_is_one(): void
    {
        $package = Package::query()->create([
            'name' => 'Monthly 1st',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'First Bill',
            'phone' => '01500000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'meta' => ['auto_invoice' => true],
        ]);

        Artisan::call('isp:generate-bills', [
            '--date' => '2026-06-01',
        ]);

        $this->assertDatabaseCount('invoices', 1);

        Artisan::call('isp:generate-bills', [
            '--date' => '2026-06-15',
        ]);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_prepaid_subscriber_gets_monthly_invoice_on_bill_day(): void
    {
        $package = Package::query()->create([
            'name' => 'Prepaid plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 600,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'Prepaid User',
            'phone' => '01500000002',
            'status' => 'active',
            'billing_mode' => 'prepaid',
            'billing_day' => 1,
            'package_id' => $package->id,
            'meta' => ['auto_invoice' => true],
        ]);

        Artisan::call('isp:generate-bills', ['--date' => '2026-06-01']);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'status' => 'open',
        ]);
    }
}
