<?php

namespace App\Services\Dashboard;

use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\PopBox;
use App\Models\SmsDeliveryReport;
use App\Services\Billing\BillingOpsMetricsService;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Models\PppSessionLog;
use App\Models\SupportTicket;
use App\Services\Optical\OpticalDashboardService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(
        protected AnalyticsReportService $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            'dashboard:snapshot:'.$tenantId,
            now()->addSeconds((int) config('dashboard.snapshot_cache_seconds', 45)),
            fn (): array => $this->buildSnapshot($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(int $tenantId): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $summary = $this->analytics->summary($from, $to, $tenantId);

        $dueCustomers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereHas('invoices', fn ($q) => $q
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0'))
            ->count();

        $openTickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->count();

        $mtOnline = MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('last_api_status', 'online')
            ->count();
        $mtTotal = MikrotikServer::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

        $oltsOnline = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', '!=', 'offline')
            ->count();
        $oltsTotal = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->count();

        $onusOnline = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->where('onu_oper_status', 'online')
            ->count();
        $onusTotal = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->count();

        $smsToday = NotificationLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('channel', 'sms')
            ->whereDate('created_at', today())
            ->count();

        $smsDelivered = NotificationLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('channel', 'sms')
            ->whereDate('created_at', today())
            ->where('status', 'sent')
            ->count();

        return array_merge($summary, [
            'due_customers' => $dueCustomers,
            'open_tickets' => $openTickets,
            'mikrotik_online' => $mtOnline,
            'mikrotik_total' => $mtTotal,
            'olts_online' => $oltsOnline,
            'olts_total' => $oltsTotal,
            'onus_online' => $onusOnline,
            'onus_total' => $onusTotal,
            'sms_today' => $smsToday,
            'sms_delivered' => $smsDelivered,
            'collected_today' => $this->collectedToday($tenantId),
        ]);
    }

    /**
     * @return array{labels: list<string>, collected: list<float>, invoiced: list<float>}
     */
    public function revenueTrend(int $days = 14, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $start = now()->subDays($days - 1)->startOfDay();

        $collected = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->where('paid_at', '>=', $start)
            ->select(DB::raw('DATE(paid_at) as day'), DB::raw('SUM(amount) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $invoiced = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('issue_date', '>=', $start->toDateString())
            ->whereNotIn('status', ['void', 'cancelled'])
            ->select(DB::raw('DATE(issue_date) as day'), DB::raw('SUM(total) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $collectedSeries = [];
        $invoicedSeries = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $labels[] = Carbon::parse($day)->format('M j');
            $collectedSeries[] = round((float) ($collected[$day] ?? 0), 2);
            $invoicedSeries[] = round((float) ($invoiced[$day] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'collected' => $collectedSeries,
            'invoiced' => $invoicedSeries,
        ];
    }

    /**
     * @return array{labels: list<string>, online: list<int>}
     */
    public function onlineUsersTrend(int $hours = 24, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            "dashboard:online_trend:{$tenantId}:{$hours}",
            now()->addMinutes((int) config('dashboard.online_trend_cache_minutes', 5)),
            fn (): array => $this->buildOnlineUsersTrend($hours, $tenantId),
        );
    }

    /**
     * @return array{labels: list<string>, online: list<int>}
     */
    private function buildOnlineUsersTrend(int $hours, int $tenantId): array
    {
        $labels = [];
        $values = [];

        for ($i = $hours - 1; $i >= 0; $i--) {
            $at = now()->subHours($i);
            $labels[] = $at->format('H:i');
            $values[] = PppSessionLog::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('started_at', '<=', $at)
                ->where(function ($q) use ($at): void {
                    $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at);
                })
                ->count();
        }

        return ['labels' => $labels, 'online' => $values];
    }

    private function collectedToday(int $tenantId): float
    {
        return (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->whereDate('paid_at', today())
            ->sum('amount');
    }

    /**
     * Executive KPI wall (4 columns) — auto-refreshed on dashboard.
     *
     * @return array{updated_at: string, columns: list<array{title: string, tone: string, cards: list<array{label: string, value: int|float|string, hint: string, url?: string}>}>}
     */
    public function kpiGrid(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $snap = $this->snapshot($tenantId);
        $c = $this->customerCounts($tenantId);
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);
        $support = $this->supportSnapshot($tenantId);

        $fmt = fn (int|float $n): string => number_format((float) $n, 0);

        return [
            'updated_at' => now()->toIso8601String(),
            'columns' => [
                [
                    'title' => 'Subscribers',
                    'tone' => 'teal',
                    'cards' => [
                        ['label' => 'Total customers', 'value' => $fmt($c['total']), 'hint' => 'All subscribers in system', 'url' => \App\Filament\Resources\CustomerResource::getUrl('index')],
                        ['label' => 'New this month', 'value' => $fmt($snap['new_subscribers'] ?? 0), 'hint' => 'Joined this month'],
                        ['label' => 'Billing clients', 'value' => $fmt($c['billable']), 'hint' => 'Active with package'],
                        ['label' => 'Online users', 'value' => $fmt($snap['online_now']), 'hint' => 'PPPoE sessions now', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl()],
                        ['label' => 'POP sites', 'value' => $fmt($c['pops']), 'hint' => 'POP / aggregation points', 'url' => \App\Filament\Resources\PopBoxResource::getUrl('index')],
                    ],
                ],
                [
                    'title' => 'Operations',
                    'tone' => 'emerald',
                    'cards' => [
                        ['label' => 'Running', 'value' => $fmt($c['running']), 'hint' => 'Not left / terminated'],
                        ['label' => 'Active status', 'value' => $fmt($c['active']), 'hint' => 'Status = active'],
                        ['label' => 'Paid up', 'value' => $fmt($c['paid_up']), 'hint' => 'No open invoice balance'],
                        ['label' => 'Suspended', 'value' => $fmt($c['suspended']), 'hint' => 'Line suspended', 'url' => \App\Filament\Resources\CustomerResource\Pages\ListSuspendedCustomers::getUrl()],
                        ['label' => 'Offline PPP', 'value' => $fmt(max(0, $c['active'] - (int) $snap['online_now'])), 'hint' => 'Active but not online'],
                    ],
                ],
                [
                    'title' => 'Billing',
                    'tone' => 'violet',
                    'cards' => [
                        ['label' => 'Due amount', 'value' => $fmt($snap['outstanding'] ?? 0), 'hint' => 'BDT outstanding'],
                        ['label' => 'Collected today', 'value' => $fmt($snap['collected_today'] ?? 0), 'hint' => 'BDT today', 'url' => \App\Filament\Pages\BillCollectionDesk::getUrl()],
                        ['label' => 'Month revenue', 'value' => $fmt($snap['collected'] ?? 0), 'hint' => 'BDT this month'],
                        ['label' => 'Partial pay', 'value' => $fmt($c['partial_invoices']), 'hint' => 'Partial invoices'],
                        ['label' => 'Service expired', 'value' => $fmt($c['service_expired']), 'hint' => 'Past valid-until date'],
                    ],
                ],
                [
                    'title' => 'Network & support',
                    'tone' => 'slate',
                    'cards' => [
                        ['label' => 'Free / VIP', 'value' => $fmt($c['waiver']), 'hint' => 'Complimentary lines'],
                        ['label' => 'Left', 'value' => $fmt($c['left']), 'hint' => 'Terminated'],
                        ['label' => 'Unpaid', 'value' => $fmt($c['unpaid']), 'hint' => 'With due balance'],
                        ['label' => 'ONU online', 'value' => $fmt($optical['online_onus']).'/'.$fmt($optical['total_onus']), 'hint' => 'GPON ONU', 'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl()],
                        ['label' => 'Open tickets', 'value' => $fmt($support['open']), 'hint' => $support['sla_breached'].' SLA overdue', 'url' => \App\Filament\Pages\SupportHub::getUrl()],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function nocSnapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $snap = $this->snapshot($tenantId);
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);

        $usersBps = BandwidthCollectionService::currentTenantLiveBps($tenantId);
        $wanBps = BandwidthCollectionService::currentWanLiveBps($tenantId);

        return array_merge($snap, $optical, [
            'active_sessions' => $snap['online_now'],
            'bandwidth_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'wan_bandwidth_mbps' => round($wanBps['down_bps'] / 1_000_000, 2),
            'users_bandwidth_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'fiber_alerts' => $optical['open_alerts'] + $optical['fiber_faults'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function billingSnapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $snap = $this->snapshot($tenantId);
        $c = $this->customerCounts($tenantId);

        return array_merge($snap, [
            'due_customers' => $snap['due_customers'] ?? 0,
            'unpaid' => $c['unpaid'],
            'partial_invoices' => $c['partial_invoices'],
            'open_invoices' => Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function supportSnapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $base = SupportTicket::withoutGlobalScopes()->where('tenant_id', $tenantId);

        return [
            'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'sla_breached' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereNotNull('sla_resolve_due_at')
                ->where('sla_resolve_due_at', '<', now())
                ->count(),
            'unassigned' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereNull('assigned_to')
                ->count(),
            'critical' => (clone $base)->where('priority', 'critical')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function gponSnapshot(?int $tenantId = null): array
    {
        return app(OpticalDashboardService::class)->snapshot($tenantId ?? TenantResolver::requiredTenantId());
    }

    /**
     * @return array<string, mixed>
     */
    public function mikrotikSnapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $snap = $this->snapshot($tenantId);

        $servers = MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'name', 'last_api_status', 'last_checked_at', 'last_error']);

        $wanBps = BandwidthCollectionService::currentWanLiveBps($tenantId);
        $usersBps = BandwidthCollectionService::currentTenantLiveBps($tenantId);

        return array_merge($snap, [
            'servers' => $servers,
            'bandwidth_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'wan_bandwidth_mbps' => round($wanBps['down_bps'] / 1_000_000, 2),
            'hotspot_active' => 0,
        ]);
    }

    /**
     * @return list<array{type: string, message: string, severity: string, at: string}>
     */
    public function liveAlerts(?int $tenantId = null, int $limit = 12): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $alerts = [];

        $support = $this->supportSnapshot($tenantId);
        if ($support['sla_breached'] > 0) {
            $alerts[] = [
                'type' => 'support',
                'message' => $support['sla_breached'].' ticket(s) past SLA',
                'severity' => 'danger',
                'at' => now()->toIso8601String(),
            ];
        }

        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);
        if (($optical['critical_onus'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'gpon',
                'message' => $optical['critical_onus'].' ONU critical signal',
                'severity' => 'warning',
                'at' => now()->toIso8601String(),
            ];
        }

        if ($snap = $this->snapshot($tenantId)) {
            if (($snap['mikrotik_total'] ?? 0) > 0 && ($snap['mikrotik_online'] ?? 0) < ($snap['mikrotik_total'] ?? 0)) {
                $down = ($snap['mikrotik_total'] - $snap['mikrotik_online']);
                $alerts[] = [
                    'type' => 'network',
                    'message' => $down.' MikroTik router(s) offline',
                    'severity' => 'danger',
                    'at' => now()->toIso8601String(),
                ];
            }
        }

        return array_slice($alerts, 0, $limit);
    }

    /**
     * @return array<string, int>
     */
    private function customerCounts(int $tenantId): array
    {
        $active = CustomerStatus::ACTIVE;
        $suspended = CustomerStatus::SUSPENDED;
        $terminated = CustomerStatus::TERMINATED;
        $today = now()->toDateString();

        $row = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->selectRaw(
                <<<'SQL'
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_ppp_online IS TRUE THEN 1 ELSE 0 END) as online_flag,
                SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as left_count,
                SUM(CASE WHEN status = ? AND package_id IS NOT NULL THEN 1 ELSE 0 END) as billable,
                SUM(CASE WHEN subscriber_type IN (?, ?) THEN 1 ELSE 0 END) as waiver,
                SUM(CASE WHEN status = ? AND EXISTS (
                    SELECT 1 FROM invoices
                    WHERE invoices.customer_id = customers.id
                    AND invoices.status IN ('open', 'partial')
                    AND (invoices.total - invoices.amount_paid) > 0
                ) THEN 1 ELSE 0 END) as unpaid,
                SUM(CASE WHEN status = ? AND NOT EXISTS (
                    SELECT 1 FROM invoices
                    WHERE invoices.customer_id = customers.id
                    AND invoices.status IN ('open', 'partial')
                    AND (invoices.total - invoices.amount_paid) > 0
                ) THEN 1 ELSE 0 END) as paid_up,
                SUM(CASE WHEN service_expires_at IS NOT NULL
                    AND service_expires_at < ?
                    AND status != ? THEN 1 ELSE 0 END) as service_expired
                SQL,
                [
                    $active,
                    $terminated,
                    $suspended,
                    $terminated,
                    $active,
                    SubscriberType::FREE,
                    SubscriberType::VIP,
                    $active,
                    $active,
                    $today,
                    $terminated,
                ],
            )
            ->first();

        $partialInvoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'partial')
            ->count();

        $pops = PopBox::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'running' => (int) ($row->running ?? 0),
            'suspended' => (int) ($row->suspended ?? 0),
            'left' => (int) ($row->left_count ?? 0),
            'billable' => (int) ($row->billable ?? 0),
            'waiver' => (int) ($row->waiver ?? 0),
            'unpaid' => (int) ($row->unpaid ?? 0),
            'paid_up' => (int) ($row->paid_up ?? 0),
            'service_expired' => (int) ($row->service_expired ?? 0),
            'partial_invoices' => $partialInvoices,
            'pops' => $pops,
            'online_flag' => (int) ($row->online_flag ?? 0),
        ];
    }

    private function estimateBandwidthMbps(int $tenantId): float
    {
        $live = BandwidthCollectionService::currentTenantLiveBps($tenantId);

        return round($live['down_bps'] / 1_000_000, 2);
    }

    private function estimateWanBandwidthMbps(int $tenantId): float
    {
        $live = BandwidthCollectionService::currentWanLiveBps($tenantId);

        return round($live['down_bps'] / 1_000_000, 2);
    }

    /**
     * Command center: billing ops + automation + SMS DLR (main dashboard).
     *
     * @return array<string, mixed>
     */
    public function commandCenterSnapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $billing = app(BillingOpsMetricsService::class)->snapshot($tenantId);
        $noc = $this->nocSnapshot($tenantId);

        $automationFailed = AutomaticProcessRun::query()
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $automationDue = AutomaticProcess::query()
            ->where('enabled', true)
            ->where('next_run_at', '<=', now()->addHour())
            ->count();

        $smsFailedDlr = SmsDeliveryReport::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('delivery_status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'billing' => $billing,
            'noc' => [
                'online_now' => $noc['online_now'] ?? 0,
                'mikrotik_online' => $noc['mikrotik_online'] ?? 0,
                'mikrotik_total' => $noc['mikrotik_total'] ?? 0,
                'bandwidth_mbps' => $noc['bandwidth_mbps'] ?? 0,
            ],
            'automation' => [
                'failed_24h' => $automationFailed,
                'due_1h' => $automationDue,
                'enabled' => AutomaticProcess::query()->where('enabled', true)->count(),
            ],
            'sms' => [
                'sent_today' => NotificationLog::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('channel', 'sms')
                    ->whereDate('created_at', today())
                    ->where('status', 'sent')
                    ->count(),
                'failed_dlr_24h' => $smsFailedDlr,
            ],
            'collected_today' => $this->collectedToday($tenantId),
        ];
    }
}
