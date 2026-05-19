<?php

namespace Tests\Feature;

use App\Models\HotspotVoucher;
use App\Models\IpPool;
use App\Models\SalesLead;
use App\Services\Hotspot\HotspotVoucherGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtendedSaasFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotspot_voucher_batch_generation(): void
    {
        $generator = app(HotspotVoucherGenerator::class);
        $batch = $generator->generateBatch(5, 12, null, 50.0, 'TEST-BATCH');

        $this->assertCount(5, $batch);
        $this->assertSame(5, HotspotVoucher::query()->where('batch_name', 'TEST-BATCH')->count());
    }

    public function test_sales_lead_and_ip_pool_models_persist(): void
    {
        $lead = SalesLead::query()->create([
            'name' => 'Test Lead',
            'phone' => '01700000000',
            'source' => 'walk_in',
            'status' => SalesLead::STATUS_NEW,
        ]);

        $pool = IpPool::query()->create([
            'name' => 'PPPoE Pool',
            'subnet' => '10.10.10.0/24',
        ]);

        $this->assertDatabaseHas('sales_leads', ['id' => $lead->id]);
        $this->assertDatabaseHas('ip_pools', ['id' => $pool->id]);
    }
}
