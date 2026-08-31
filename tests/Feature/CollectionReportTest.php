<?php

namespace Tests\Feature;

use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('payment-collection-report', 'web');
        $user = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin-cr@isp.com']);
        $user->assignRole('Super Admin');

        return $user;
    }

    private function staff(): User
    {
        Permission::findOrCreate('payment-collection', 'web');
        $user = User::factory()->create(['name' => 'Field Staff', 'email' => 'staff-cr@isp.com']);
        $user->givePermissionTo('payment-collection');

        return $user;
    }

    public function test_collection_report_page_is_responsive_for_staff(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('collection-report.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('cr-page', $html);
        $this->assertStringContainsString('col-12 col-sm-6 col-lg-3', $html);
        $this->assertStringContainsString('cr-table-wrap', $html);
        $this->assertStringContainsString('min-height:44px', $html);
        $this->assertStringContainsString('collection-report-form', $html);
        $this->assertStringContainsString('All collectors', $html);
        $this->assertStringContainsString('Staff name', $html);
    }

    public function test_staff_see_only_their_collections_and_admin_sees_names(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        CustomersInfo::create([
            'customer_unique_id' => 'CR-1',
            'customer_name' => 'Pay Client',
            'mobile' => '01700000991',
            'status' => 'active',
        ]);
        CollectionSummary::create([
            'customer_collection_unique_id' => 'CR-1',
            'collection_date' => now(),
            'collection_amount' => 400,
            'collected_by' => $staff->email,
            'invoice_no' => 100001,
            'payment_status' => 'paid',
        ]);
        CollectionSummary::create([
            'customer_collection_unique_id' => 'CR-1',
            'collection_date' => now(),
            'collection_amount' => 900,
            'collected_by' => $admin->email,
            'invoice_no' => 100002,
            'payment_status' => 'paid',
        ]);

        $staffHtml = $this->actingAs($staff)
            ->get(route('collection-report.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Your collections only', $staffHtml);
        $this->assertStringContainsString('Field Staff', $staffHtml);
        $this->assertStringNotContainsString('All collectors', $staffHtml);

        $staffJson = $this->actingAs($staff)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('collection-report.index', [
                'fromDate' => now()->toDateString(),
                'toDate' => now()->toDateString(),
            ]))
            ->assertOk()
            ->json();
        $this->assertSame(400.0, (float) $staffJson['summary']['total']);
        $this->assertSame(1, (int) $staffJson['summary']['count']);
        $this->assertSame('Field Staff', $staffJson['data'][0]['collected_by']);

        $adminJson = $this->actingAs($admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('collection-report.index', [
                'fromDate' => now()->toDateString(),
                'toDate' => now()->toDateString(),
            ]))
            ->assertOk()
            ->json();
        $this->assertSame(1300.0, (float) $adminJson['summary']['total']);
        $names = collect($adminJson['data'])->pluck('collected_by')->all();
        $this->assertContains('Field Staff', $names);
        $this->assertContains('Main Admin', $names);
    }
}
