<?php

namespace Tests\Feature;

use App\Models\CollectorCollection;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Subscribers\CustomerDeletionService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_customer_with_collector_collection_can_be_deleted(): void
    {
        $collector = User::factory()->create(['tenant_id' => 1]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'DEL001',
            'name' => 'Delete Me',
            'phone' => '01710000001',
            'status' => 'active',
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        CollectorCollection::query()->create([
            'tenant_id' => 1,
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'amount' => 100,
            'collected_at' => now(),
        ]);

        app(CustomerDeletionService::class)->delete($customer);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('collector_collections', ['customer_id' => $customer->id]);
    }
}
