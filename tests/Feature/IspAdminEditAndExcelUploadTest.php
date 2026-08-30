<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Billing\CustomerExcelImporter;
use App\Services\Saas\OperatorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IspAdminEditAndExcelUploadTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $owner = User::factory()->create(['email' => 'owner-edit@isp.com']);
        $owner->assignRole('Super Admin');

        return $owner;
    }

    public function test_owner_can_edit_operator_and_change_plan(): void
    {
        $this->actingAs($this->owner());

        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Old Co',
            'contact_name' => 'Old Admin',
            'email' => 'old-admin@isp.com',
            'password' => 'password12',
            'plan' => 'starter',
            'billing_cycle' => 'monthly',
        ]);

        app(OperatorProvisioningService::class)->updateProfile($operator, [
            'company' => 'New Co',
            'contact_name' => 'New Admin',
            'email' => 'new-admin@isp.com',
            'phone' => '01841558023',
            'password' => 'newpass123',
        ]);

        $fresh = $operator->fresh('user');
        $this->assertSame('New Co', $fresh->company);
        $this->assertSame('new-admin@isp.com', $fresh->email);
        $this->assertSame('new-admin@isp.com', $fresh->user->email);
        $this->assertSame('New Admin', $fresh->user->name);

        $pro = SaasPlan::query()->where('slug', 'pro')->first()
            ?? SaasPlan::query()->where('slug', '!=', 'starter')->firstOrFail();

        app(OperatorProvisioningService::class)->applyPlan($fresh, $pro, 'yearly');

        $fresh->refresh();
        $this->assertSame($pro->slug, $fresh->plan);
        $this->assertSame('yearly', $fresh->billing_cycle);
        $this->assertSame((int) $pro->max_customers, (int) $fresh->max_customers);
    }

    public function test_excel_import_creates_customer_for_operator_tenant(): void
    {
        $this->actingAs($this->owner());
        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Upload ISP',
            'contact_name' => 'Upload Admin',
            'email' => 'upload-admin@isp.com',
            'password' => 'password12',
            'plan' => 'starter',
        ]);
        $buyer = User::where('email', 'upload-admin@isp.com')->firstOrFail();
        $this->actingAs($buyer);

        $path = storage_path('app/tmp-excel-test.csv');
        @mkdir(dirname($path), 0777, true);
        $fh = fopen($path, 'w');
        fputcsv($fh, CustomerExcelImporter::headers());
        fputcsv($fh, [
            'Excel Client',
            '01700000088',
            '',
            'exceluser1',
            'Pass1234',
            '',
            600,
            5,
            now()->addMonth()->format('Y-m-d'),
            'Sylhet',
            '',
            '',
            'active',
            0,
            '',
            'from test',
        ]);
        fclose($fh);

        $stats = app(CustomerExcelImporter::class)->import($path);
        @unlink($path);

        $this->assertSame(1, $stats['created']);
        $customer = CustomersInfo::query()->where('customer_name', 'Excel Client')->first();
        $this->assertNotNull($customer);
        $this->assertSame($operator->id, (int) $customer->saas_operator_id);
        $this->assertSame(600.0, (float) $customer->billing->monthly_rent);
        $this->assertSame(5, (int) $customer->billing->billing_day);
    }
}
