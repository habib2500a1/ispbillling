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
}
