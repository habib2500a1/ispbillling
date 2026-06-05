<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerPackage;
use App\Models\SupportTicket;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResellerPortalSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Reseller $reseller;

    private Customer $customer;

    private Invoice $invoice;

    private SupportTicket $ticket;

    private Reseller $childReseller;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);

        $this->reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Smoke Franchise',
            'code' => 'RSL-SMOKE',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'wallet_balance' => 5000,
            'is_active' => true,
            'api_access_enabled' => true,
            'white_label_enabled' => true,
            'own_integrations_enabled' => true,
            'portal_password' => Hash::make('secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '15 Mbps',
            'download_mbps' => 15,
            'upload_mbps' => 15,
            'price_monthly' => 900,
            'is_active' => true,
        ]);

        ResellerPackage::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $this->reseller->id,
            'package_id' => $package->id,
            'selling_price' => 900,
            'wholesale_price' => 650,
            'is_active' => true,
        ]);

        $this->customer = Customer::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $this->reseller->id,
            'name' => 'Smoke Subscriber',
            'phone' => '01715550001',
            'address' => 'Dhaka',
            'package_id' => $package->id,
            'customer_code' => 'SMK-001',
            'status' => 'active',
            'billing_day' => 5,
        ]);

        $this->invoice = Invoice::query()->create([
            'tenant_id' => 1,
            'customer_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 900,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 900,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'payment',
        ]);

        ResellerCommission::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $this->reseller->id,
            'payment_id' => $payment->id,
            'customer_id' => $this->customer->id,
            'gross_amount' => 500,
            'commission_amount' => 50,
            'status' => ResellerCommission::STATUS_PENDING,
            'earned_at' => now(),
        ]);

        $this->ticket = SupportTicket::query()->create([
            'ticket_number' => 'TKT-SMOKE-0001',
            'tenant_id' => 1,
            'customer_id' => $this->customer->id,
            'channel' => 'reseller_portal',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Smoke ticket',
            'description' => 'Line down',
            'status' => 'open',
        ]);

        $this->childReseller = Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $this->reseller->id,
            'name' => 'Child Partner',
            'code' => 'RSL-CHILD',
            'franchise_type' => ResellerType::SUB_RESELLER,
            'commission_type' => 'percent',
            'commission_value' => 5,
            'wallet_balance' => 100,
            'is_active' => true,
        ]);
    }

    public function test_login_page_includes_portal_stylesheets(): void
    {
        $this->get(route('reseller.login'))
            ->assertOk()
            ->assertSee('reseller-portal-pro.css', false)
            ->assertSee('reseller-portal-compat.css', false);
    }

    public function test_authenticated_layout_includes_compat_stylesheet(): void
    {
        $this->actingAs($this->reseller, 'reseller')
            ->get(route('reseller.dashboard'))
            ->assertOk()
            ->assertSee('reseller-portal-compat.css', false)
            ->assertSee('rsl-page', false);
    }

    public function test_package_quote_json_ok(): void
    {
        $this->actingAs($this->reseller, 'reseller')
            ->getJson(route('reseller.customers.package-quote', [
                'customer' => $this->customer,
                'package_id' => $this->customer->package_id,
            ]))
            ->assertOk()
            ->assertJsonStructure(['current_package', 'new_package', 'net_due']);
    }

    /**
     * @dataProvider getPageRoutesProvider
     */
    public function test_get_reseller_portal_pages_return_success(string $routeName, array $params = []): void
    {
        $params = $this->resolveRouteParams($params);

        if (! Route::has($routeName)) {
            $this->markTestSkipped("Route {$routeName} is not registered.");
        }

        $url = route($routeName, $params);

        $response = $this->actingAs($this->reseller, 'reseller')->get($url);

        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 302], true),
            "GET {$url} ({$routeName}) returned {$response->getStatusCode()}"
        );

        if ($response->isOk() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->assertDontSee('Server Error', false);
            $response->assertDontSee('Whoops', false);
        }
    }

    public static function getPageRoutesProvider(): array
    {
        return [
            'dashboard' => ['reseller.dashboard', []],
            'hub' => ['reseller.hub', []],
            'customers index' => ['reseller.customers.index', []],
            'customers create' => ['reseller.customers.create', []],
            'customers show' => ['reseller.customers.show', ['customer' => '__CUSTOMER__']],
            'customers edit' => ['reseller.customers.edit', ['customer' => '__CUSTOMER__']],
            'customers collect' => ['reseller.customers.collect', ['customer' => '__CUSTOMER__']],
            'customer transfer create' => ['reseller.customer-transfers.create', ['customer' => '__CUSTOMER__']],
            'onu index' => ['reseller.onu.index', []],
            'onu show' => ['reseller.onu.show', ['customer' => '__CUSTOMER__']],
            'invoices index' => ['reseller.invoices.index', []],
            'invoices show' => ['reseller.invoices.show', ['invoice' => '__INVOICE__']],
            'tickets index' => ['reseller.tickets.index', []],
            'tickets create' => ['reseller.tickets.create', []],
            'tickets show' => ['reseller.tickets.show', ['ticket' => '__TICKET_NUMBER__']],
            'reports' => ['reseller.reports.index', []],
            'reports export' => ['reseller.reports.export', []],
            'network' => ['reseller.network.index', []],
            'network session' => ['reseller.network.session', ['customer' => '__CUSTOMER__']],
            'sub-resellers index' => ['reseller.sub-resellers.index', []],
            'sub-resellers create' => ['reseller.sub-resellers.create', []],
            'sub-resellers show' => ['reseller.sub-resellers.show', ['child' => '__CHILD__']],
            'wallet overview' => ['reseller.wallet.overview', []],
            'due account' => ['reseller.due-account', []],
            'enterprise reports' => ['reseller.reports.enterprise', []],
            'announcements' => ['reseller.announcements.index', []],
            'security' => ['reseller.security.index', []],
            'customer transfers' => ['reseller.customer-transfers.index', []],
            'api keys' => ['reseller.api-keys.index', []],
            'branding' => ['reseller.branding.edit', []],
            'internal tickets' => ['reseller.internal-tickets.index', []],
            'commissions' => ['reseller.commissions.index', []],
            'wallet' => ['reseller.wallet.index', []],
            'settlements' => ['reseller.settlements.index', []],
            'activity' => ['reseller.activity.index', []],
            'notifications' => ['reseller.notifications.index', []],
            'settings' => ['reseller.settings.index', []],
            'settings branding' => ['reseller.settings.branding', []],
            'settings sms' => ['reseller.settings.sms', []],
            'settings payment' => ['reseller.settings.payment', []],
            'staff index' => ['reseller.staff.index', []],
            'staff create' => ['reseller.staff.create', []],
            'realtime config' => ['reseller.realtime.config', []],
            'realtime poll' => ['reseller.realtime.poll', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function resolveRouteParams(array $params): array
    {
        $map = [
            '__CUSTOMER__' => $this->customer->id,
            '__INVOICE__' => $this->invoice->id,
            '__TICKET__' => $this->ticket->id,
            '__CHILD__' => $this->childReseller->id,
            '__TICKET_NUMBER__' => $this->ticket->ticket_number,
        ];

        foreach ($params as $key => $value) {
            if (is_string($value) && isset($map[$value])) {
                $params[$key] = $map[$value];
            }
        }

        return $params;
    }
}
