<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikPppImportService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MikrotikPppImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_csv_import_creates_and_updates_subscribers(): void
    {
        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test MT',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $csv = "username,password,profile,name,phone\n";
        $csv .= "pppuser1,pass111,10M,Test User,01711111111\n";
        $path = storage_path('app/test-import.csv');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'import.csv', 'text/csv', null, true);

        $result = app(MikrotikPppImportService::class)->importFromFile($server, $file, [
            'code_format' => 'secret_as_code',
        ]);

        $this->assertSame(1, $result['created']);
        $customer = Customer::query()->where('mikrotik_secret_name', 'pppuser1')->first();
        $this->assertNotNull($customer);
        $this->assertSame('pppuser1', $customer->customer_code);
        $this->assertSame('pppuser1', $customer->radius_username);
        $this->assertSame('excel', $customer->import_source);

        $csv2 = "username,password\npppuser1,newpass222\n";
        file_put_contents($path, $csv2);
        $file2 = new UploadedFile($path, 'import.csv', 'text/csv', null, true);
        $result2 = app(MikrotikPppImportService::class)->importFromFile($server, $file2);
        $this->assertSame(1, $result2['updated']);

        @unlink($path);
    }

    public function test_import_selected_requires_selection(): void
    {
        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'MT Select',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'x',
            'is_enabled' => true,
        ]);

        $result = app(MikrotikPppImportService::class)->importSelectedFromRouter($server, []);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_purge_mikrotik_imported_deletes_only_marked(): void
    {
        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'MT Purge',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'x',
            'is_enabled' => true,
        ]);

        Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'imp1',
            'name' => 'Imported',
            'phone' => '01711111111',
            'status' => 'active',
            'import_source' => 'mikrotik',
            'mikrotik_server_id' => $server->id,
        ]);
        Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'man1',
            'name' => 'Manual',
            'phone' => '01722222222',
            'status' => 'active',
        ]);

        $result = app(MikrotikPppImportService::class)->purgeMikrotikImported(1, $server->id);
        $this->assertSame(1, $result['deleted']);
        $this->assertDatabaseMissing('customers', ['customer_code' => 'imp1']);
        $this->assertDatabaseHas('customers', ['customer_code' => 'man1']);
    }

    public function test_bandwidth_resolve_matches_mikrotik_secret_name(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'CUST-2605-0001',
            'name' => 'Secret Match',
            'phone' => '01700000001',
            'status' => 'active',
            'mikrotik_secret_name' => 'router_login_99',
            'radius_username' => 'router_login_99',
        ]);

        $found = \App\Support\CustomerPppLoginResolver::resolve(1, 'router_login_99');

        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);
    }
}
