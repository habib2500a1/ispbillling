<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Ai\AiChurnScoringService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChurnScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scores_customer_with_service_expiry(): void
    {
        TenantResolver::fake(1);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Test',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Risk User',
            'phone' => '01711112222',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'R100001',
            'service_expires_at' => now()->addDays(3),
        ]);

        $score = app(AiChurnScoringService::class)->scoreCustomer($customer);

        $this->assertGreaterThanOrEqual(30, $score['score']);
        $this->assertContains('Service expires in', implode(' ', $score['reasons']));
    }
}
