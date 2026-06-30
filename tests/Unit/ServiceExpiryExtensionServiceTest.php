<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Billing\ServiceExpiryExtensionService;
use App\Support\CustomerStatus;
use App\Support\PaymentRenewalPolicy;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceExpiryExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extends_from_previous_expiry_when_policy_set_on_payment(): void
    {
        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        $previousExpiry = now()->subDays(3)->startOfDay();
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Renewal Policy User',
            'phone' => '01710009999',
            'status' => CustomerStatus::EXPIRED,
            'billing_day' => 10,
            'package_id' => $package->id,
            'service_expires_at' => $previousExpiry->toDateString(),
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'paid_at' => now(),
            'meta' => ['renewal_policy' => PaymentRenewalPolicy::FROM_PREVIOUS_EXPIRY],
        ]);

        app(ServiceExpiryExtensionService::class)->extendForPaidCycle($customer, $payment);

        $customer->refresh();
        $expected = $previousExpiry->copy()->addMonthNoOverflow()->toDateString();
        $this->assertSame($expected, $customer->service_expires_at?->toDateString());
        $this->assertSame(CustomerStatus::ACTIVE, $customer->status);
    }

    public function test_updates_billing_day_from_payment_date_when_enabled(): void
    {
        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Billing Day User',
            'phone' => '01710008888',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 5,
            'package_id' => $package->id,
            'service_expires_at' => now()->toDateString(),
        ]);

        $paidAt = now()->startOfMonth()->day(15);
        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'paid_at' => $paidAt,
            'meta' => [],
        ]);

        app(ServiceExpiryExtensionService::class)->extendForPaidCycle($customer, $payment);

        $this->assertSame(15, (int) $customer->fresh()->billing_day);
    }

    public function test_skips_billing_day_update_when_flag_set(): void
    {
        $package = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => 'monthly',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Skip Billing Day',
            'phone' => '01710007777',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 5,
            'package_id' => $package->id,
            'service_expires_at' => now()->toDateString(),
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'paid_at' => now()->startOfMonth()->day(20),
            'meta' => ['skip_billing_date_update' => true],
        ]);

        app(ServiceExpiryExtensionService::class)->extendForPaidCycle($customer, $payment);

        $this->assertSame(5, (int) $customer->fresh()->billing_day);
    }
}
