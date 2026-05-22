<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Payments\MfsCustomerReferenceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MfsCustomerPhoneMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_registered_sender_phone(): void
    {
        config(['mfs_personal.sms_ingest.match_sender_phone' => true]);

        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Phone User',
            'customer_code' => 'PH001',
            'phone' => '01711223344',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $resolved = app(MfsCustomerReferenceMatcher::class)->resolve(
            1,
            'You have received Tk 500. TrxID BKASHPHONE1',
            null,
            'BKASHPHONE1',
            '01711223344',
        );

        $this->assertSame('sms_sender_phone', $resolved['matched_by']);
        $this->assertSame($customer->id, $resolved['customer']?->id);
    }

    public function test_numeric_id_0790_matches_customer_790(): void
    {
        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        Customer::query()->create([
            'name' => 'ID User',
            'customer_code' => '790',
            'phone' => '01799887766',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        $resolved = app(MfsCustomerReferenceMatcher::class)->resolve(
            1,
            'Received Tk 500 Ref 0790 TrxID BKASHID790',
            '0790',
            'BKASHID790',
            null,
        );

        $this->assertSame('sms_reference', $resolved['matched_by']);
        $this->assertSame('790', $resolved['customer']?->customer_code);
    }
}
