<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberTypePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function seedCustomer(string $type = SubscriberType::STANDARD, string $status = CustomerStatus::ACTIVE): Customer
    {
        $package = Package::query()->create([
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Policy Test',
            'phone' => '01799001122',
            'customer_code' => 'POL'.random_int(1000, 9999),
            'status' => $status,
            'subscriber_type' => $type,
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
            'account_balance' => 0,
            'network_access_state' => 'active',
            'service_expires_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_free_subscriber_skips_invoice_generation(): void
    {
        $customer = $this->seedCustomer(SubscriberType::FREE);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);

        $this->assertNull($invoice);
    }

    public function test_vip_subscriber_still_generates_invoice(): void
    {
        $customer = $this->seedCustomer(SubscriberType::VIP);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);

        $this->assertNotNull($invoice);
    }

    public function test_vip_exempt_from_auto_suspend_when_overdue(): void
    {
        config(['network.auto_suspend_enabled' => true]);

        $customer = $this->seedCustomer(SubscriberType::VIP);
        $customer->invoices()->create([
            'tenant_id' => $customer->tenant_id,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $coordinator = app(NetworkAccessCoordinator::class);

        $this->assertSame('active', $coordinator->desiredNetworkAccessState($customer->fresh()));
    }

    public function test_free_exempt_from_service_expiry_demotion(): void
    {
        config(['network.service_expiry_enforced' => true]);

        $customer = $this->seedCustomer(SubscriberType::FREE);
        $coordinator = app(NetworkAccessCoordinator::class);
        $coordinator->syncCustomer($customer->fresh());

        $customer->refresh();
        $this->assertNotSame(CustomerStatus::EXPIRED, $customer->status);
    }
}
