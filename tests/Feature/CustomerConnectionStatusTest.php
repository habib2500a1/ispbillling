<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PppSessionLog;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Network\CustomerConnectionStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConnectionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_session_shows_duration(): void
    {
        $customer = $this->makeCustomer();
        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'test-active-'.$customer->id,
            'username' => 'user1',
            'started_at' => now()->subHours(2),
            'status' => 'active',
        ]);

        $summary = app(CustomerConnectionStatusService::class)->summary($customer->fresh());

        $this->assertTrue($summary['online']);
        $this->assertNotNull($summary['connection_duration']);
        $this->assertSame('—', $summary['last_disconnect_formatted']);
    }

    public function test_ended_session_shows_last_disconnect(): void
    {
        $customer = $this->makeCustomer();
        $ended = now()->subDay();
        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'test-ended-'.$customer->id,
            'username' => 'user1',
            'started_at' => now()->subDays(2),
            'ended_at' => $ended,
            'status' => 'ended',
        ]);

        $summary = app(CustomerConnectionStatusService::class)->summary($customer->fresh());

        $this->assertFalse($summary['online']);
        $this->assertSame($ended->format('d M Y, h:i A'), $summary['last_disconnect_formatted']);
    }

    public function test_isp_digital_row_maps_online_flag(): void
    {
        $importer = new \App\Services\Import\IspDigitalCustomerImporter;
        $customer = $importer->importRow([
            'CustomerId' => 'T'.random_int(10000, 99999),
            'CustomerName' => 'Online Test',
            'MobileNumber' => '01711111111',
            'UserName' => 'online.test',
            'ShortStatus' => 'active',
            'Status' => 'Active',
            'IsOnline' => true,
            'ConnectivityStatus' => 'Online',
            'Package' => '10Mbps',
            'MonthlyBill' => 400,
        ], true);

        $this->assertTrue($customer->is_ppp_online);
        $this->assertSame('active', $customer->network_access_state);
    }

    public function test_isp_digital_import_sets_mikrotik_profile_on_package(): void
    {
        $importer = new \App\Services\Import\IspDigitalCustomerImporter;
        $code = 'T'.random_int(10000, 99999);

        $customer = $importer->importRow([
            'CustomerId' => $code,
            'CustomerName' => 'Profile Test',
            'MobileNumber' => '01722222222',
            'UserName' => 'profile.test',
            'ShortStatus' => 'active',
            'Status' => 'Active',
            'Package' => 'SyncPkgMbps',
            'PackageSpeed' => 'SyncPkgMbps/Packages>>1',
            'MonthlyBill' => 500,
        ], true);

        $package = Package::query()->findOrFail($customer->package_id);
        $this->assertSame('SyncPkgMbps', $package->name);
        $this->assertSame('Packages>>1', $package->mikrotik_profile_name);
    }

    public function test_isp_digital_import_links_existing_package_by_mikrotik_profile(): void
    {
        $existing = Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Packages>>9',
            'mikrotik_profile_name' => 'Packages>>9',
            'type' => 'residential',
            'download_mbps' => 25,
            'upload_mbps' => 25,
            'price_monthly' => 600,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $importer = new \App\Services\Import\IspDigitalCustomerImporter;
        $customer = $importer->importRow([
            'CustomerId' => 'T'.random_int(10000, 99999),
            'CustomerName' => 'Link By Profile',
            'MobileNumber' => '01733333333',
            'UserName' => 'link.profile',
            'ShortStatus' => 'active',
            'Status' => 'Active',
            'Package' => 'DisplayOnlyMbps',
            'PackageSpeed' => 'DisplayOnlyMbps/Packages>>9',
            'MonthlyBill' => 600,
        ], true);

        $this->assertSame($existing->id, $customer->package_id);
    }

    public function test_bill_collection_detail_includes_connection(): void
    {
        $customer = $this->makeCustomer();
        $row = app(BillCollectionSearchService::class)->find($customer->id);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('connection', $row);
        $this->assertArrayHasKey('online', $row['connection']);
    }

    private function makeCustomer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'Test 10',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
        ]);

        return Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'C'.random_int(1000, 9999),
            'name' => 'Conn Test',
            'phone' => '01700000001',
            'package_id' => $package->id,
            'status' => 'active',
        ]);
    }
}
