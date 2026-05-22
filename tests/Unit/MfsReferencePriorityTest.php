<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Payments\MfsCustomerReferenceMatcher;
use App\Support\MfsPaymentReferenceParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MfsReferencePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_782_matches_customer_code_0782(): void
    {
        $package = $this->package();
        Customer::query()->create([
            'name' => 'Fariya',
            'customer_code' => '0782',
            'phone' => '01339078960',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $this->assertContains('0782', MfsPaymentReferenceParser::numericVariants('782'));

        $resolved = app(MfsCustomerReferenceMatcher::class)->resolve(
            1,
            'You have received payment Tk 500.00 from 01841558023. Ref 782. TrxID DEN5HXLU09',
            '782.',
            'DEN5HXLU09',
            '01841558023',
        );

        $this->assertSame('sms_reference', $resolved['matched_by']);
        $this->assertSame('0782', $resolved['customer']?->customer_code);
    }

    public function test_ref_in_sms_does_not_fallback_to_sender_phone_when_id_unknown(): void
    {
        $package = $this->package();
        Customer::query()->create([
            'name' => 'Habib',
            'customer_code' => 'habibfree',
            'phone' => '01841558023',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $resolved = app(MfsCustomerReferenceMatcher::class)->resolve(
            1,
            'You have received payment Tk 500.00 from 01841558023. Ref 99999. TrxID TESTREF99',
            '99999',
            'TESTREF99',
            '01841558023',
        );

        $this->assertNull($resolved['customer']);
        $this->assertSame('sms_reference_unmatched', $resolved['matched_by']);
    }

    private function package(): Package
    {
        return Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);
    }
}
