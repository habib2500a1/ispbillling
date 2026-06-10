<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Import\ISPTrack\ISPTrackImportOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ISPTrackImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::query()->create([
            'id' => 1,
            'name' => 'Test ISP',
            'slug' => 'test-isp',
            'is_active' => true,
        ]);
    }

    public function test_full_import_from_example_json(): void
    {
        $path = storage_path('app/import/isptrack-export.example.json');
        $this->assertFileExists($path);

        $report = app(ISPTrackImportOrchestrator::class)->run(
            $path,
            1,
            [0, 1, 2, 3, 4, 5],
            dryRun: false,
            force: false,
            skipNetwork: true,
        );

        $this->assertArrayHasKey('phases', $report);
        $this->assertTrue(Package::query()->where('name', '20Mbps Home')->exists());
        $this->assertTrue(Customer::query()->where('customer_code', 'C-1024')->exists());

        $customer = Customer::query()->where('customer_code', 'C-1024')->first();
        $this->assertNotNull($customer);
        $this->assertSame('user1024', $customer->mikrotik_secret_name);
        $this->assertSame('isptrack', $customer->import_source);

        $this->assertTrue(
            Invoice::query()->where('customer_id', $customer->id)->where('invoice_number', 'IT-BILL-2026-06-001')->exists()
        );
    }

    public function test_dry_run_does_not_create_customers(): void
    {
        $path = storage_path('app/import/isptrack-export.example.json');

        app(ISPTrackImportOrchestrator::class)->run(
            $path,
            1,
            [2],
            dryRun: true,
        );

        $this->assertFalse(Customer::query()->where('customer_code', 'C-1024')->exists());
    }
}
