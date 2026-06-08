<?php

namespace App\Services\Dashboard;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\MikrotikDashboard;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\SmsGatewaySetup;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\HotspotVoucherResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\ListSuspendedCustomers;
use App\Filament\Resources\ResellerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PendingGatewayPaymentResource;
use App\Filament\Resources\PopBoxResource;
use App\Models\Customer;
use App\Models\PendingGatewayPayment;
use App\Models\HotspotVoucher;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PopBox;
use App\Services\Billing\BillingAccountListCounts;
use App\Services\Notifications\SmsBalanceFetcher;
use App\Models\User;
use App\Support\CompanyBranding;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * FlixBD ISP Platform — native operations dashboard (not a third-party UI clone).
 */
final class OperationsDashboardService
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly SubscriberSegmentMetrics $segments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $userKey = (int) ($user?->id ?? 0);

        return Cache::remember(
            "ops_dashboard:{$tenantId}:{$userKey}:".now()->format('Y-m-d-H-i'),
            60,
            fn (): array => $this->build($tenantId, StaffCapability::for($user)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId, StaffCapability $capability): array
    {
        $snap = $this->metrics->snapshot($tenantId);
        $c = $this->customerBreakdown($tenantId);
        $seg = $this->segments->forTenant($tenantId);
        $billingCounts = $this->safeBillingCounts();
        $sales = $this->salesTotals($tenantId);
        $sms = app(SmsBalanceFetcher::class)->fetch();

        $online = (int) ($snap['online_now'] ?? 0);
        $active = (int) ($c['active'] ?? 0);
        $pops = PopBox::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $revenueTrend = ($capability->canBilling() || $capability->canReports())
            ? $this->metrics->revenueTrend(14, $tenantId)
            : null;

        $payload = [
            'updated_at' => now()->toIso8601String(),
            'company' => CompanyBranding::name(),
            'highlights' => $this->highlights($snap, $sms, $tenantId, $capability),
            'primary' => $this->primaryKpis($snap, $c, $seg, $online, $capability),
            'sections' => $this->groupedSections($tenantId, $snap, $c, $billingCounts, $sales, $online, $active, $pops, $capability),
            'feeds' => $this->feeds($tenantId, $capability),
            'mfs_pending_verify' => $this->mfsPendingVerify($tenantId, $capability),
            'billing_aside' => $this->billingAside($snap, $revenueTrend, $capability),
        ];

        return $payload;
    }

    /**
     * Compact sidebar (replaces large revenue trend chart).
     *
     * @param  array<string, mixed>  $snap
     * @param  array{labels: list<string>, collected: list<float>, invoiced: list<float>}|null  $revenueTrend
     * @return array{title: string, stats: list<array{label: string, value: string, hint?: string, url?: string}>, links: list<array{label: string, url: string, icon: string}>}|null
     */
    private function billingAside(array $snap, ?array $revenueTrend, StaffCapability $capability): ?array
    {
        $stats = [];
        $links = [];

        if ($capability->canPayments() || $capability->canBilling()) {
            $collected14 = $revenueTrend ? array_sum($revenueTrend['collected'] ?? []) : 0.0;
            $invoiced14 = $revenueTrend ? array_sum($revenueTrend['invoiced'] ?? []) : 0.0;

            $stats = [
                [
                    'label' => 'Collected today',
                    'value' => number_format((float) ($snap['collected_today'] ?? 0), 0).' ৳',
                    'hint' => 'Cash & gateway',
                    'url' => BillCollectionDesk::getUrl(),
                ],
                [
                    'label' => 'Collected · 14 days',
                    'value' => number_format($collected14, 0).' ৳',
                    'hint' => 'Rolling total',
                    'url' => BillCollectionDesk::getUrl(),
                ],
                [
                    'label' => 'Invoiced · 14 days',
                    'value' => number_format($invoiced14, 0).' ৳',
                    'hint' => 'Issue date',
                    'url' => InvoiceResource::getUrl('index'),
                ],
                [
                    'label' => 'Outstanding',
                    'value' => number_format((float) ($snap['outstanding'] ?? 0), 0).' ৳',
                    'hint' => 'Open invoices',
                    'url' => BillCollectionDesk::getUrl(),
                ],
                [
                    'label' => 'Due accounts',
                    'value' => number_format((int) ($snap['due_customers'] ?? 0)),
                    'hint' => 'With balance',
                    'url' => CustomerResource::getUrl('index'),
                ],
            ];

            $links[] = ['label' => 'Collection desk', 'url' => BillCollectionDesk::getUrl(), 'icon' => 'heroicon-m-banknotes'];
            $links[] = ['label' => 'Payments', 'url' => \App\Filament\Resources\PaymentResource::getUrl('index'), 'icon' => 'heroicon-m-currency-dollar'];
            $links[] = ['label' => 'Invoices', 'url' => InvoiceResource::getUrl('index'), 'icon' => 'heroicon-m-document-text'];
            if ($capability->canReports()) {
                $links[] = ['label' => 'Billing reports', 'url' => BillingOverview::getUrl(['tab' => 'reports']), 'icon' => 'heroicon-m-chart-bar'];
            }
        }

        if ($capability->canMikrotik()) {
            $links[] = ['label' => 'Online users', 'url' => OnlineClientsMonitoring::getUrl(), 'icon' => 'heroicon-m-signal'];
            $links[] = ['label' => 'Network hub', 'url' => MikrotikDashboard::getUrl(), 'icon' => 'heroicon-m-server'];
        }

        if ($capability->canSupport()) {
            $links[] = ['label' => 'Support hub', 'url' => SupportHub::getUrl(), 'icon' => 'heroicon-m-lifebuoy'];
        }

        if ($capability->canCustomers()) {
            $links[] = ['label' => 'Subscribers', 'url' => CustomerResource::getUrl('index'), 'icon' => 'heroicon-m-users'];
        }

        if ($stats === [] && $links === []) {
            return null;
        }

        return [
            'title' => $stats !== [] ? 'Billing snapshot' : 'Quick actions',
            'stats' => $stats,
            'links' => $links,
        ];
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array{balance: float|null, provider?: string}  $sms
     * @return list<array{label: string, value: string, url?: string}>
     */
    private function highlights(array $snap, array $sms, int $tenantId, StaffCapability $capability): array
    {
        $rows = [];

        if ($capability->canSms()) {
            $rows[] = ['label' => 'SMS balance', 'value' => $sms['balance'] !== null ? number_format((float) $sms['balance'], 1).' BDT' : '—', 'url' => SmsGatewaySetup::getUrl()];
        }

        if ($capability->canPayments() || $capability->canBilling()) {
            $rows[] = ['label' => 'Collected today', 'value' => number_format((float) ($snap['collected_today'] ?? 0), 0).' BDT', 'url' => BillCollectionDesk::getUrl()];
            $rows[] = ['label' => 'Collection rate', 'value' => number_format((float) ($snap['collection_rate'] ?? 0), 1).'%', 'url' => BillCollectionDesk::getUrl()];
            $pendingMfs = $this->pendingMfsCount($tenantId);
            if ($pendingMfs > 0 && PendingGatewayPaymentResource::canViewAny()) {
                $rows[] = [
                    'label' => 'MFS verify pending',
                    'value' => (string) $pendingMfs,
                    'url' => PendingGatewayPaymentResource::getUrl(),
                ];
            }
        }

        if ($capability->isTenantAdmin()) {
            $rows[] = ['label' => 'Tenant', 'value' => '#'.$tenantId];
        }

        return $rows;
    }

    /**
     * @return array<string, list<mixed>>
     */
    /**
     * @return array{count: int, url: ?string, items: list<array{gateway: string, trx: string, amount: string, customer: string, at: string, url: string}>}
     */
    private function mfsPendingVerify(int $tenantId, StaffCapability $capability): array
    {
        if (! $capability->canPayments() && ! $capability->canBilling()) {
            return ['count' => 0, 'url' => null, 'items' => []];
        }

        if (! PendingGatewayPaymentResource::canViewAny()) {
            return ['count' => 0, 'url' => null, 'items' => []];
        }

        $count = $this->pendingMfsCount($tenantId);
        $url = PendingGatewayPaymentResource::getUrl();

        $items = PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (PendingGatewayPayment $row): array => [
                'gateway' => strtoupper((string) $row->gateway),
                'trx' => (string) $row->transaction_id,
                'amount' => number_format((float) $row->amount, 2),
                'customer' => (string) ($row->customer?->name ?? '—'),
                'at' => $row->created_at?->diffForHumans() ?? '',
                'url' => $url,
            ])
            ->all();

        return ['count' => $count, 'url' => $url, 'items' => $items];
    }

    private function pendingMfsCount(int $tenantId): int
    {
        return (int) PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->count();
    }

    private function feeds(int $tenantId, StaffCapability $capability): array
    {
        return [
            'recent_payments' => ($capability->canPayments() || $capability->canBilling())
                ? $this->recentPayments($tenantId)
                : [],
            'recent_customers' => $capability->canCustomers()
                ? $this->recentCustomers($tenantId)
                : [],
            'invoices' => $capability->canBilling() ? $this->latestInvoices($tenantId) : [],
            'upcoming_expire' => $capability->canCustomers() ? $this->upcomingExpire($tenantId) : [],
            'latest_expired' => $capability->canCustomers() ? $this->latestExpired($tenantId) : [],
            'top_due' => ($capability->canBilling() || $capability->canCustomers()) ? $this->topDue($tenantId) : [],
            'activity_log' => $this->activityLog($tenantId, $capability),
        ];
    }

    /**
     * Unified recent activity stream (payments, invoices, customers, expirations).
     *
     * @return list<array{type: string, summary: string, detail: string, at: string, url?: string, sort: int}>
     */
    private function activityLog(int $tenantId, StaffCapability $capability): array
    {
        $events = [];

        if ($capability->canPayments() || $capability->canBilling()) {
            foreach ($this->recentPayments($tenantId) as $row) {
                $events[] = [
                    'type' => 'Payment',
                    'summary' => $row['customer'],
                    'detail' => $row['gateway'].' · '.$row['amount'].' BDT',
                    'at' => $row['at'],
                    'url' => $row['url'] ?? null,
                    'sort' => strtotime($row['at'] ?: 'now') ?: 0,
                ];
            }
        }

        if ($capability->canBilling()) {
            foreach ($this->latestInvoices($tenantId) as $row) {
                $events[] = [
                    'type' => 'Invoice',
                    'summary' => $row['user'],
                    'detail' => $row['no'].' · '.$row['amount'].' BDT',
                    'at' => '—',
                    'url' => $row['url'],
                    'sort' => 0,
                ];
            }
        }

        if ($capability->canCustomers()) {
            foreach ($this->recentCustomers($tenantId) as $row) {
                $events[] = [
                    'type' => 'Customer',
                    'summary' => $row['user'],
                    'detail' => 'New signup · '.$row['bill'].' BDT/mo',
                    'at' => $row['joined'],
                    'url' => $row['url'],
                    'sort' => strtotime($row['joined'] ?: 'now') ?: 0,
                ];
            }

            foreach ($this->upcomingExpire($tenantId) as $row) {
                $events[] = [
                    'type' => 'Expiring',
                    'summary' => $row['user'],
                    'detail' => 'Expires '.$row['expire'],
                    'at' => $row['expire'],
                    'url' => $row['url'],
                    'sort' => strtotime($row['expire'] ?: 'now') ?: 0,
                ];
            }
        }

        usort($events, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_slice(array_map(fn (array $e): array => [
            'type' => $e['type'],
            'summary' => $e['summary'],
            'detail' => $e['detail'],
            'at' => $e['at'],
            'url' => $e['url'] ?? null,
        ], $events), 0, 12);
    }

    /** @return list<array{gateway: string, trx: string, amount: string, customer: string, at: string, url?: string}> */
    private function recentPayments(int $tenantId): array
    {
        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->with('customer:id,name,customer_code')
            ->orderByDesc('paid_at')
            ->limit(8)
            ->get(['id', 'customer_id', 'gateway', 'method', 'gateway_transaction_id', 'amount', 'paid_at', 'receipt_number'])
            ->map(fn (Payment $p): array => [
                'gateway' => strtoupper((string) ($p->gateway ?: $p->method ?: '—')),
                'trx' => $p->gateway_transaction_id ?: $p->receipt_number ?: '—',
                'amount' => number_format((float) $p->amount, 2),
                'customer' => $p->customer?->name ?: $p->customer?->customer_code ?: '—',
                'at' => $p->paid_at?->format('d M, H:i') ?? '—',
                'url' => $p->customer_id
                    ? CustomerResource::getUrl('view', ['record' => $p->customer_id])
                    : null,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, int>  $c
     * @return list<array{label: string, value: string, hint: string, url?: string}>
     */
    /**
     * @param  array<string, int>  $seg
     */
    private function primaryKpis(array $snap, array $c, array $seg, int $online, StaffCapability $capability): array
    {
        $n = fn (int|float $v): string => number_format((float) $v, 0);
        $kpis = [];

        if ($capability->canCustomers()) {
            $partners = (int) ($seg['active_reseller_partners'] ?? 0);
            $kpis[] = [
                'label' => 'Direct active',
                'value' => $n($seg['direct_active'] ?? $c['active']),
                'hint' => 'Own / ISP clients',
                'url' => CustomerResource::getUrl('active'),
                'tone' => 'teal',
            ];
            $kpis[] = [
                'label' => 'Reseller active',
                'value' => $n($seg['reseller_clients_active'] ?? 0),
                'hint' => $partners > 0
                    ? "{$partners} partner(s) · client lines"
                    : 'Under reseller',
                'url' => ResellerResource::getUrl('index'),
                'tone' => 'cyan',
            ];
        }

        if ($capability->canMikrotik()) {
            $kpis[] = [
                'label' => 'Online (direct)',
                'value' => $n($seg['direct_online'] ?? $online),
                'hint' => 'PPPoE · own clients',
                'url' => OnlineClientsMonitoring::getUrl(),
                'tone' => 'sky',
            ];
            $kpis[] = [
                'label' => 'Online (reseller)',
                'value' => $n($seg['reseller_clients_online'] ?? 0),
                'hint' => 'PPPoE · reseller clients',
                'url' => OnlineClientsMonitoring::getUrl(),
                'tone' => 'blue',
            ];
        }

        if ($capability->canPayments() || $capability->canBilling()) {
            $kpis[] = ['label' => 'Collected today', 'value' => $n($snap['collected_today'] ?? 0), 'hint' => 'BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'green'];
            $kpis[] = ['label' => 'Collection rate', 'value' => number_format((float) ($snap['collection_rate'] ?? 0), 1).'%', 'hint' => 'This month', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'emerald'];
            $kpis[] = ['label' => 'Outstanding', 'value' => $n($snap['outstanding'] ?? 0), 'hint' => 'BDT due', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'amber'];
            $kpis[] = ['label' => 'Due accounts', 'value' => $n($snap['due_customers'] ?? 0), 'hint' => 'Open balance', 'url' => CustomerResource::getUrl('index'), 'tone' => 'rose'];
        }

        if ($capability->canSupport()) {
            $kpis[] = ['label' => 'Support open', 'value' => $n($snap['open_tickets'] ?? 0), 'hint' => 'Tickets', 'url' => SupportHub::getUrl(), 'tone' => 'violet'];
        }

        return $kpis;
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
        StaffCapability $capability,
    ): array {
        $n = fn (int|float $v): string => number_format((float) $v, 0);
        $money = fn (float $v): string => number_format($v, 0);
        $offline = max(0, $active - $online);
        $vouchers = HotspotVoucher::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', HotspotVoucher::STATUS_AVAILABLE)
            ->count();

        $sections = [];

        if ($capability->canCustomers()) {
            $sections[] = [
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
            ];
        }

        if ($capability->canBilling() || $capability->canPayments()) {
            $sections[] = [
                'title' => 'Billing & collection',
                'icon' => 'heroicon-o-banknotes',
                'accent' => 'green',
                'cards' => [
                    ['label' => 'Collection today', 'value' => $money($sales['today']).' BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'green'],
                    ['label' => 'Yesterday', 'value' => $money($sales['yesterday']).' BDT', 'url' => BillingOverview::getUrl(['tab' => 'analytics']), 'tone' => 'teal'],
                    ['label' => 'This month', 'value' => $money($sales['month']).' BDT', 'url' => BillingOverview::getUrl(['tab' => 'analytics']), 'tone' => 'sky'],
                    ['label' => 'Month collected', 'value' => $money((float) ($snap['collected'] ?? 0)).' BDT', 'url' => BillingOverview::getUrl(['tab' => 'analytics']), 'tone' => 'green'],
                    ['label' => 'Outstanding', 'value' => $money((float) ($snap['outstanding'] ?? 0)).' BDT', 'url' => BillCollectionDesk::getUrl(), 'tone' => 'amber'],
                    ['label' => 'Due subscribers', 'value' => $n($snap['due_customers'] ?? 0), 'url' => BillCollectionDesk::getUrl(), 'tone' => 'rose'],
                ],
            ];
        }

        if ($capability->canNetwork() || $capability->canSms()) {
            $networkCards = [];

            if ($capability->canMikrotik()) {
                $networkCards[] = ['label' => 'Online / Active', 'value' => $n($online).' / '.$n($active), 'url' => OnlineClientsMonitoring::getUrl(), 'tone' => 'sky'];
                $networkCards[] = ['label' => 'Offline (active)', 'value' => $n($offline), 'url' => OnlineClientsMonitoring::getUrl(), 'tone' => 'slate'];
                $networkCards[] = ['label' => 'POP sites', 'value' => $n($pops), 'url' => PopBoxResource::getUrl('index'), 'tone' => 'teal'];
                $networkCards[] = ['label' => 'MikroTik', 'value' => $n($snap['mikrotik_online'] ?? 0).'/'.$n($snap['mikrotik_total'] ?? 0), 'url' => MikrotikDashboard::getUrl(), 'tone' => 'orange'];
            }

            if ($capability->canOlt()) {
                $networkCards[] = ['label' => 'ONU online', 'value' => $n($snap['onus_online'] ?? 0).'/'.$n($snap['onus_total'] ?? 0), 'url' => OpticalMonitoringHub::getUrl(), 'tone' => 'violet'];
            }

            if ($capability->canMikrotik()) {
                $networkCards[] = ['label' => 'Vouchers ready', 'value' => $n($vouchers), 'url' => HotspotVoucherResource::getUrl('index'), 'tone' => 'amber'];
            }

            if ($capability->canSms()) {
                $networkCards[] = ['label' => 'SMS sent today', 'value' => $n($snap['sms_today'] ?? 0), 'url' => SmsGatewaySetup::getUrl(), 'tone' => 'green'];
            }

            if ($networkCards !== []) {
                $sections[] = [
                    'title' => 'Network & messaging',
                    'icon' => 'heroicon-o-signal',
                    'accent' => 'violet',
                    'cards' => $networkCards,
                ];
            }
        }

        return $sections;
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

    /** @return list<array{user: string, bill: string, joined: string, url: string}> */
    private function recentCustomers(int $tenantId): array
    {
        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->with('package:id,price_monthly')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'name', 'customer_code', 'created_at', 'package_id'])
            ->map(fn (Customer $c): array => [
                'user' => $c->name ?: $c->customer_code,
                'bill' => number_format((float) ($c->package?->price_monthly ?? 0), 0),
                'joined' => $c->created_at?->format('d M, H:i') ?? '—',
                'url' => CustomerResource::getUrl('view', ['record' => $c]),
            ])
            ->all();
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
