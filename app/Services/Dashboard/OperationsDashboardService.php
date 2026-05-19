<?php

namespace App\Services\Dashboard;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingDashboard;
use App\Filament\Pages\MikrotikDashboard;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\SmsGatewaySetup;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\HotspotVoucherResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\ListSuspendedCustomers;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PopBoxResource;
use App\Models\Customer;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PopBox;
use App\Services\Billing\BillingAccountListCounts;
use App\Services\Notifications\SmsBalanceFetcher;
use App\Support\CompanyBranding;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * FlixBD ISP Platform — native operations dashboard (not a third-party UI clone).
 */
final class OperationsDashboardService
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            "ops_dashboard:{$tenantId}:".now()->format('Y-m-d-H-i'),
            60,
            fn (): array => $this->build($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $snap = $this->metrics->snapshot($tenantId);
        $c = $this->customerBreakdown($tenantId);
        $billingCounts = $this->safeBillingCounts();
        $sales = $this->salesTotals($tenantId);
        $sms = app(SmsBalanceFetcher::class)->fetch();

        $online = (int) ($snap['online_now'] ?? 0);
        $active = (int) ($c['active'] ?? 0);
        $pops = PopBox::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        return [
            'updated_at' => now()->toIso8601String(),
            'company' => CompanyBranding::name(),
            'highlights' => [
                ['label' => 'SMS balance', 'value' => $sms['balance'] !== null ? number_format((float) $sms['balance'], 1).' BDT' : '—', 'url' => SmsGatewaySetup::getUrl()],
                ['label' => 'Collected today', 'value' => number_format((float) ($snap['collected_today'] ?? 0), 0).' BDT', 'url' => BillCollectionDesk::getUrl()],
                ['label' => 'Tenant', 'value' => '#'.$tenantId],
            ],
            'primary' => $this->primaryKpis($snap, $c, $online, $active),
            'sections' => $this->groupedSections($tenantId, $snap, $c, $billingCounts, $sales, $online, $active, $pops),
            'feeds' => [
                'invoices' => $this->latestInvoices($tenantId),
                'upcoming_expire' => $this->upcomingExpire($tenantId),
                'latest_expired' => $this->latestExpired($tenantId),
                'top_due' => $this->topDue($tenantId),
            ],
            'revenue_chart' => $this->metrics->revenueTrend(14, $tenantId),
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, int>  $c
     * @return list<array{label: string, value: string, hint: string, url?: string}>
     */
    private function primaryKpis(array $snap, array $c, int $online, int $active): array
    {
        $n = fn (int|float $v): string => number_format((float) $v, 0);

        return [
            ['label' => 'Active subscribers', 'value' => $n($c['active']), 'hint' => 'Live accounts', 'url' => CustomerResource::getUrl('active'), 'tone' => 'teal'],
            ['label' => 'Online now', 'value' => $n($online), 'hint' => 'PPPoE sessions', 'url' => OnlineClientsMonitoring::getUrl(), 'tone' => 'sky'],
            ['label' => 'Collected today', 'value' => $n($snap['collected_today'] ?? 0), 'hint' => 'BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'green'],
            ['label' => 'Outstanding', 'value' => $n($snap['outstanding'] ?? 0), 'hint' => 'BDT due', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'amber'],
            ['label' => 'Due accounts', 'value' => $n($snap['due_customers'] ?? 0), 'hint' => 'Open balance', 'url' => CustomerResource::getUrl('index'), 'tone' => 'rose'],
            ['label' => 'Support open', 'value' => $n($snap['open_tickets'] ?? 0), 'hint' => 'Tickets', 'url' => SupportHub::getUrl(), 'tone' => 'violet'],
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, int>  $c
     * @param  array<string, int>  $billingCounts
     * @param  array{today: float, yesterday: float, month: float}  $sales
     * @return list<array{title: string, icon: string, cards: list<array{label: string, value: string, url?: string}>}>
     */
    private function groupedSections(
        int $tenantId,
        array $snap,
        array $c,
        array $billingCounts,
        array $sales,
        int $online,
        int $active,
        int $pops,
    ): array {
        $n = fn (int|float $v): string => number_format((float) $v, 0);
        $money = fn (float $v): string => number_format($v, 0);
        $offline = max(0, $active - $online);
        $vouchers = HotspotVoucher::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', HotspotVoucher::STATUS_AVAILABLE)
            ->count();

        return [
            [
                'title' => 'Subscribers',
                'icon' => 'heroicon-o-users',
                'accent' => 'teal',
                'cards' => [
                    ['label' => 'All accounts', 'value' => $n($c['total']), 'url' => CustomerResource::getUrl('index'), 'tone' => 'teal'],
                    ['label' => 'Home / PPPoE', 'value' => $n($c['home']), 'url' => CustomerResource::getUrl('index'), 'tone' => 'sky'],
                    ['label' => 'Hotspot', 'value' => $n($c['hotspot']), 'url' => CustomerResource::getUrl('index'), 'tone' => 'violet'],
                    ['label' => 'Pending KYC', 'value' => $n($billingCounts['pending'] ?? 0), 'url' => CustomerResource::getUrl('pending'), 'tone' => 'amber'],
                    ['label' => 'New today', 'value' => $n($billingCounts['today'] ?? 0), 'url' => CustomerResource::getUrl('today'), 'tone' => 'green'],
                    ['label' => 'Expire ≤3d', 'value' => $n($billingCounts['expire_3'] ?? 0), 'url' => CustomerResource::getUrl('expire-3'), 'tone' => 'orange'],
                    ['label' => 'Expire ≤7d', 'value' => $n($billingCounts['expire_7'] ?? 0), 'url' => CustomerResource::getUrl('expire-7'), 'tone' => 'orange'],
                    ['label' => 'Expired', 'value' => $n($billingCounts['expired'] ?? 0), 'url' => CustomerResource::getUrl('expired'), 'tone' => 'rose'],
                    ['label' => 'Suspended', 'value' => $n($c['suspended']), 'url' => ListSuspendedCustomers::getUrl(), 'tone' => 'rose'],
                    ['label' => 'Left', 'value' => $n($c['left']), 'url' => CustomerResource::getUrl('left'), 'tone' => 'slate'],
                ],
            ],
            [
                'title' => 'Billing & collection',
                'icon' => 'heroicon-o-banknotes',
                'accent' => 'green',
                'cards' => [
                    ['label' => 'Collection today', 'value' => $money($sales['today']).' BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'green'],
                    ['label' => 'Yesterday', 'value' => $money($sales['yesterday']).' BDT', 'url' => BillingDashboard::getUrl(), 'tone' => 'teal'],
                    ['label' => 'This month', 'value' => $money($sales['month']).' BDT', 'url' => BillingDashboard::getUrl(), 'tone' => 'sky'],
                    ['label' => 'Month collected', 'value' => $money((float) ($snap['collected'] ?? 0)).' BDT', 'url' => BillingDashboard::getUrl(), 'tone' => 'green'],
                    ['label' => 'Outstanding', 'value' => $money((float) ($snap['outstanding'] ?? 0)).' BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'amber'],
                    ['label' => 'Due subscribers', 'value' => $n($snap['due_customers'] ?? 0), 'url' => BillCollectionDesk::getUrl(), 'tone' => 'rose'],
                ],
            ],
            [
                'title' => 'Network & messaging',
                'icon' => 'heroicon-o-signal',
                'accent' => 'violet',
                'cards' => [
                    ['label' => 'Online / Active', 'value' => $n($online).' / '.$n($active), 'url' => OnlineClientsMonitoring::getUrl(), 'tone' => 'sky'],
                    ['label' => 'Offline (active)', 'value' => $n($offline), 'url' => OnlineClientsMonitoring::getUrl(), 'tone' => 'slate'],
                    ['label' => 'POP sites', 'value' => $n($pops), 'url' => PopBoxResource::getUrl('index'), 'tone' => 'teal'],
                    ['label' => 'MikroTik', 'value' => $n($snap['mikrotik_online'] ?? 0).'/'.$n($snap['mikrotik_total'] ?? 0), 'url' => MikrotikDashboard::getUrl(), 'tone' => 'orange'],
                    ['label' => 'ONU online', 'value' => $n($snap['onus_online'] ?? 0).'/'.$n($snap['onus_total'] ?? 0), 'url' => OpticalMonitoringHub::getUrl(), 'tone' => 'violet'],
                    ['label' => 'Vouchers ready', 'value' => $n($vouchers), 'url' => HotspotVoucherResource::getUrl('index'), 'tone' => 'amber'],
                    ['label' => 'SMS sent today', 'value' => $n($snap['sms_today'] ?? 0), 'url' => SmsGatewaySetup::getUrl(), 'tone' => 'green'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function customerBreakdown(int $tenantId): array
    {
        $row = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->selectRaw(
                <<<'SQL'
                COUNT(*) FILTER (WHERE status != ?) as total,
                COUNT(*) FILTER (WHERE status = ?) as active,
                COUNT(*) FILTER (WHERE status = ?) as suspended,
                COUNT(*) FILTER (WHERE status = ?) as left_count,
                COUNT(*) FILTER (
                    WHERE status != ? AND package_id IS NOT NULL
                    AND EXISTS (SELECT 1 FROM packages p WHERE p.id = customers.package_id AND p.type = 'hotspot')
                ) as hotspot,
                COUNT(*) FILTER (
                    WHERE status != ? AND package_id IS NOT NULL
                    AND EXISTS (SELECT 1 FROM packages p WHERE p.id = customers.package_id AND COALESCE(p.type, '') != 'hotspot')
                ) as home
                SQL,
                [
                    CustomerStatus::TERMINATED,
                    CustomerStatus::ACTIVE,
                    CustomerStatus::SUSPENDED,
                    CustomerStatus::TERMINATED,
                    CustomerStatus::TERMINATED,
                    CustomerStatus::TERMINATED,
                ],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'suspended' => (int) ($row->suspended ?? 0),
            'left' => (int) ($row->left_count ?? 0),
            'hotspot' => (int) ($row->hotspot ?? 0),
            'home' => (int) ($row->home ?? 0),
        ];
    }

    /** @return array<string, int> */
    private function safeBillingCounts(): array
    {
        try {
            return app(BillingAccountListCounts::class)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{today: float, yesterday: float, month: float} */
    private function salesTotals(int $tenantId): array
    {
        $base = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT);

        return [
            'today' => (float) (clone $base)->whereDate('paid_at', today())->sum('amount'),
            'yesterday' => (float) (clone $base)->whereDate('paid_at', today()->subDay())->sum('amount'),
            'month' => (float) (clone $base)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
        ];
    }

    /** @return list<array{no: string, user: string, amount: string, url: string}> */
    private function latestInvoices(int $tenantId): array
    {
        return Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('customer:id,name,customer_code')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Invoice $inv): array => [
                'no' => $inv->invoice_number,
                'user' => $inv->customer?->name ?? $inv->customer?->customer_code ?? '—',
                'amount' => number_format((float) $inv->total, 0),
                'url' => InvoiceResource::getUrl('edit', ['record' => $inv]),
            ])
            ->all();
    }

    /** @return list<array{user: string, bill: string, expire: string, url: string}> */
    private function upcomingExpire(int $tenantId): array
    {
        $today = now()->toDateString();
        $limit = now()->addDays(7)->toDateString();

        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '>=', $today)
            ->whereDate('service_expires_at', '<=', $limit)
            ->with('package:id,price_monthly')
            ->orderBy('service_expires_at')
            ->limit(8)
            ->get(['id', 'name', 'customer_code', 'service_expires_at', 'package_id'])
            ->map(fn (Customer $c): array => [
                'user' => $c->name ?: $c->customer_code,
                'bill' => number_format((float) ($c->package?->price_monthly ?? 0), 0),
                'expire' => $c->service_expires_at?->format('d M, H:i') ?? '—',
                'url' => CustomerResource::getUrl('view', ['record' => $c]),
            ])
            ->all();
    }

    /** @return list<array{user: string, bill: string, expire: string, url: string}> */
    private function latestExpired(int $tenantId): array
    {
        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q): void {
                $q->where('status', CustomerStatus::EXPIRED)
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('service_expires_at')
                            ->whereDate('service_expires_at', '<', now()->toDateString());
                    });
            })
            ->with('package:id,price_monthly')
            ->orderByDesc('service_expires_at')
            ->limit(8)
            ->get(['id', 'name', 'customer_code', 'service_expires_at', 'package_id'])
            ->map(fn (Customer $c): array => [
                'user' => $c->name ?: $c->customer_code,
                'bill' => number_format((float) ($c->package?->price_monthly ?? 0), 0),
                'expire' => $c->service_expires_at?->format('d M, H:i') ?? '—',
                'url' => CustomerResource::getUrl('view', ['record' => $c]),
            ])
            ->all();
    }

    /** @return list<array{user: string, due: string, url: string}> */
    private function topDue(int $tenantId): array
    {
        return Customer::withoutGlobalScopes()
            ->where('customers.tenant_id', $tenantId)
            ->where('customers.status', CustomerStatus::ACTIVE)
            ->join('invoices', 'invoices.customer_id', '=', 'customers.id')
            ->whereIn('invoices.status', ['open', 'partial', 'draft'])
            ->groupBy('customers.id', 'customers.name', 'customers.customer_code')
            ->select(
                'customers.id',
                'customers.name',
                'customers.customer_code',
                DB::raw('SUM(invoices.total - invoices.amount_paid) as due_total'),
            )
            ->havingRaw('SUM(invoices.total - invoices.amount_paid) > 0')
            ->orderByDesc('due_total')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'user' => $row->name ?: $row->customer_code,
                'due' => number_format((float) $row->due_total, 0),
                'url' => CustomerResource::getUrl('view', ['record' => $row->id]),
            ])
            ->all();
    }
}
