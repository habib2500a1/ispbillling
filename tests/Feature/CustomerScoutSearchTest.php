<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Search\CustomerScoutSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerScoutSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Laravel\Scout\EngineManager::class)) {
            $this->markTestSkipped('laravel/scout not installed — run composer update');
        }

        config(['scout.driver' => 'collection']);
        config(['customer_search.use_scout' => true]);
        config(['scout.queue' => false]);
    }

    public function test_scout_finds_customer_by_name_with_typo_tolerance_path(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'SCOUT001',
            'name' => 'Habibur Rahman',
            'phone' => '01710001111',
            'mikrotik_secret_name' => 'habib_ppp',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $customer->searchable();

        $ids = app(CustomerScoutSearchService::class)->searchIds('habib', 10, 1);

        $this->assertNotNull($ids);
        $this->assertContains($customer->id, $ids);
    }

    public function test_bill_collection_search_uses_scout_when_enabled(): void
    {
        Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'SCOUT002',
            'name' => 'Akib Hasan',
            'phone' => '01710002222',
            'status' => 'active',
            'billing_day' => 1,
        ])->searchable();

        $results = app(BillCollectionSearchService::class)->search('akib');

        $this->assertTrue($results->contains(fn (array $row): bool => $row['customer_code'] === 'SCOUT002'));
    }
}
