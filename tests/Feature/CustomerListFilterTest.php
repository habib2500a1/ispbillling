<?php

namespace Tests\Feature;

use App\Models\CustomersInfo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-13 12:00:00'));
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $this->actingAs($user);
    }

    public function test_joined_today_and_month_follow_connection_date_with_created_at_fallback(): void
    {
        $this->customer('TODAY-CONNECTION', '2026-10-13 08:30:00', '2026-08-01 10:00:00');
        $this->customer('MONTH-CONNECTION', '2026-10-08 08:30:00', '2026-08-02 10:00:00');
        $this->customer('TODAY-FALLBACK', null, '2026-10-13 09:00:00');
        // An explicit connection date takes precedence over record creation time.
        $this->customer('OLD-CONNECTION', '2026-09-30 08:30:00', '2026-10-13 10:00:00');

        $today = $this->postJson(route('customers.data'), $this->dataTableRequest('joined_today'));
        $today->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->assertEqualsCanonicalizing(
            ['TODAY-CONNECTION', 'TODAY-FALLBACK'],
            collect($today->json('data'))->pluck('customer_unique_id')->all(),
        );

        $month = $this->postJson(route('customers.data'), $this->dataTableRequest('joined_month'));
        $month->assertOk()->assertJsonPath('recordsFiltered', 3);
        $this->assertEqualsCanonicalizing(
            ['TODAY-CONNECTION', 'MONTH-CONNECTION', 'TODAY-FALLBACK'],
            collect($month->json('data'))->pluck('customer_unique_id')->all(),
        );
    }

    private function customer(string $id, ?string $connectionDate, string $createdAt): CustomersInfo
    {
        $customer = CustomersInfo::create([
            'customer_unique_id' => $id,
            'customer_name' => $id,
            'status' => 'active',
            'connection_date' => $connectionDate,
        ]);

        DB::table('customers_infos')->where('id', $customer->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $customer;
    }

    /** @return array<string, mixed> */
    private function dataTableRequest(string $filter): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'filter' => $filter,
            'search' => ['value' => ''],
            'columns' => [],
            'order' => [],
        ];
    }
}
