<?php

namespace App\Services\Dashboard;

use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\NotificationLog;
use App\Models\OnuSignalLog;
use App\Models\Outage;
use App\Models\Payment;
use App\Models\PopBox;
use App\Models\OltHealthLog;
use App\Models\SmsDeliveryReport;
use App\Services\Billing\BillingOpsMetricsService;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Models\PppSessionLog;
use App\Models\SupportTicket;
use App\Services\Optical\OpticalDashboardService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
                ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                ->whereRaw('(total - amount_paid) > 0.009'))
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

        $segments = app(SubscriberSegmentMetrics::class)->forTenant($tenantId);

        return array_merge($summary, $segments, [
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

        return Cache::remember(
            "dashboard:revenue_trend:{$tenantId}:{$days}",
            now()->addMinutes((int) config('dashboard.revenue_trend_cache_minutes', 5)),
            fn (): array => $this->buildRevenueTrend($days, $tenantId),
        );
    }

    /**
     * @return array{labels: list<string>, collected: list<float>, invoiced: list<float>}
     */
    private function buildRevenueTrend(int $days, int $tenantId): array
    {
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
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $this->buildOnlineUsersTrendPgsql($hours, $tenantId);
        }

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

    /**
     * @return array{labels: list<string>, online: list<int>}
     */
    private function buildOnlineUsersTrendPgsql(int $hours, int $tenantId): array
    {
        $start = now()->subHours($hours - 1)->startOfHour();
        $end = now()->startOfHour();

        $rows = DB::select(
            <<<'SQL'
            WITH buckets AS (
                SELECT generate_series(?::timestamp, ?::timestamp, '1 hour'::interval) AS bucket_at
            )
            SELECT
                b.bucket_at,
                COUNT(p.id)::int AS online_count
            FROM buckets b
            LEFT JOIN ppp_session_logs p
                ON p.tenant_id = ?
                AND p.status = 'active'
                AND p.started_at <= b.bucket_at
                AND (p.ended_at IS NULL OR p.ended_at >= b.bucket_at)
            GROUP BY b.bucket_at
            ORDER BY b.bucket_at
            SQL,
            [$start, $end, $tenantId],
        );

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = Carbon::parse($row->bucket_at)->format('H:i');
            $values[] = (int) $row->online_count;
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

        return Cache::remember(
            'dashboard:noc_snapshot:'.$tenantId,
            now()->addSeconds((int) config('dashboard.noc_snapshot_cache_seconds', 45)),
            fn (): array => $this->buildNocSnapshot($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNocSnapshot(int $tenantId): array
    {
        $snap = $this->snapshot($tenantId);
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);
        $customerCounts = $this->customerCounts($tenantId);
        $oltHealth = app(\App\Services\Olt\OltNocDashboardService::class)->snapshot($tenantId);
        $oltRows = collect($oltHealth['olts'] ?? []);
        $downCustomers = $this->downCustomersCollection($tenantId);
        $activeOutages = Outage::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->currentlyActive()
            ->with('area:id,name')
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();
        $hotPonPorts = $this->hotPonPorts($tenantId);
        $telemetry = $this->accessTelemetry($tenantId, $oltRows);
        $oltReachability = $this->oltReachabilitySummary($tenantId, $oltRows);

        $usersBps = BandwidthCollectionService::currentTenantLiveBps($tenantId);
        $wanBps = BandwidthCollectionService::currentWanLiveBps($tenantId);
        $wanInterfaces = BandwidthCollectionService::latestWanInterfaceSnapshots($tenantId);
        $bandwidthTrend = BandwidthCollectionService::aggregateLiveMbpsPerSecond($tenantId, 10, 18);

        $linkDown = $oltRows->sum(function (array $olt): int {
            $total = (int) ($olt['interfaces_total'] ?? 0);
            $up = (int) ($olt['interfaces_up'] ?? 0);

            return max(0, $total - $up);
        });

        $partialOltCount = $oltRows->filter(function (array $olt): bool {
            $status = (string) ($olt['status'] ?? '');
            $total = (int) ($olt['interfaces_total'] ?? 0);
            $up = (int) ($olt['interfaces_up'] ?? 0);
            $onusOffline = (int) ($olt['onus_offline'] ?? 0);

            return $status !== 'offline'
                && (($total > 0 && $up < $total) || $onusOffline > 0);
        })->count();

        return array_merge($snap, $optical, [
            'active_sessions' => $snap['online_now'],
            'bandwidth_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'wan_bandwidth_mbps' => round($wanBps['down_bps'] / 1_000_000, 2),
            'users_bandwidth_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'users_download_mbps' => round($usersBps['down_bps'] / 1_000_000, 2),
            'users_upload_mbps' => round($usersBps['up_bps'] / 1_000_000, 2),
            'wan_download_mbps' => round($wanBps['down_bps'] / 1_000_000, 2),
            'wan_upload_mbps' => round($wanBps['up_bps'] / 1_000_000, 2),
            'user_down' => max(0, (int) ($customerCounts['active'] ?? 0) - (int) ($snap['online_now'] ?? 0)),
            'link_down' => $linkDown,
            'wan_interfaces' => $wanInterfaces,
            'bandwidth_trend' => $bandwidthTrend,
            'access_telemetry' => $telemetry,
            'olt_reachability' => $oltReachability,
            'hot_pon_ports' => $hotPonPorts,
            'top_impact' => $this->topImpactRanking($downCustomers, $oltRows, $activeOutages, $hotPonPorts),
            'olt_offline' => (int) ($oltHealth['olt_offline'] ?? 0),
            'olt_partial' => $partialOltCount,
            'olt_high_cpu' => (int) ($oltHealth['olt_high_cpu'] ?? 0),
            'olt_high_memory' => (int) ($oltHealth['olt_high_memory'] ?? 0),
            'olt_unhealthy' => (int) ($oltHealth['olt_unhealthy'] ?? 0),
            'olt_avg_health' => $oltHealth['avg_health_score'] ?? null,
            'olt_rows' => $oltRows->values()->all(),
            'down_users' => $this->downUsersSummary($downCustomers),
            'root_causes' => $this->rootCauseBreakdown($downCustomers),
            'zone_impact' => $this->zoneImpactSummary($downCustomers, $activeOutages),
            'area_impact' => $this->areaImpactSummary($downCustomers, $activeOutages),
            'active_outages' => [
                'count' => $activeOutages->count(),
                'items' => $activeOutages->map(fn (Outage $outage): array => [
                    'id' => (int) $outage->id,
                    'title' => $outage->title,
                    'area_id' => (int) ($outage->area_id ?? 0),
                    'area' => $outage->area?->name ?? 'All areas',
                    'started' => $outage->started_at?->diffForHumans() ?? 'Unknown',
                ])->all(),
            ],
            'critical_onu_list' => app(OpticalDashboardService::class)
                ->criticalOnus($tenantId, 6)
                ->map(fn (Device $device): array => [
                    'customer_id' => (int) ($device->customer_id ?? 0),
                    'serial' => (string) ($device->serial_number ?? '—'),
                    'customer' => (string) ($device->customer?->name ?? 'Unassigned'),
                    'olt' => (string) ($device->olt?->display_name ?? 'Unknown OLT'),
                    'rx_dbm' => $device->rx_power_dbm !== null ? round((float) $device->rx_power_dbm, 2) : null,
                    'status' => (string) ($device->onu_oper_status ?? 'unknown'),
                ])->all(),
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
                ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
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
     * @return list<array{id: int, name: string, code: string, login: string, zone: string, server: string, last_seen: string, reason: string}>
     */
    private function downUsersSummary(Collection $customers, int $limit = 8): array
    {
        return $customers->take($limit)->map(function (Customer $customer): array {
            $lastSeen = $customer->ppp_last_seen_at ?? $customer->lastEndedPppSession?->ended_at;

            return [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'code' => (string) ($customer->customer_code ?? '—'),
                'login' => $customer->pppLoginName(),
                'zone' => (string) ($customer->zone?->name ?? '—'),
                'server' => (string) ($customer->mikrotikServer?->name ?? 'Unassigned'),
                'last_seen' => $lastSeen?->diffForHumans() ?? 'Never',
                'reason' => $this->downCustomerReason($customer),
            ];
        })->all();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function downCustomersCollection(int $tenantId): Collection
    {
        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function ($q): void {
                $q->where('is_ppp_online', false)->orWhereNull('is_ppp_online');
            })
            ->with([
                'area:id,name',
                'zone:id,name,area_id',
                'mikrotikServer:id,name',
                'lastEndedPppSession',
            ])
            ->withExists([
                'invoices as has_due_invoice' => fn ($q) => $q
                    ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                    ->whereRaw('(total - amount_paid) > 0.009'),
            ])
            ->orderByRaw('CASE WHEN service_expires_at IS NOT NULL AND service_expires_at < ? THEN 0 ELSE 1 END', [now()->toDateString()])
            ->orderBy('ppp_last_seen_at')
            ->get([
                'id',
                'tenant_id',
                'name',
                'customer_code',
                'status',
                'area_id',
                'zone_id',
                'mikrotik_server_id',
                'radius_username',
                'mikrotik_secret_name',
                'service_expires_at',
                'ppp_last_seen_at',
            ]);
    }

    private function downCustomerReason(Customer $customer): string
    {
        $lastSeen = $customer->ppp_last_seen_at ?? $customer->lastEndedPppSession?->ended_at;

        return match (true) {
            $customer->status === CustomerStatus::SUSPENDED => 'Suspended',
            $customer->isServiceExpired() => 'Expired / billing due',
            (bool) ($customer->has_due_invoice ?? false) => 'Due balance',
            $lastSeen !== null && $lastSeen->greaterThan(now()->subMinutes(30)) => 'Recently disconnected',
            $lastSeen === null => 'Never came online',
            default => 'Offline / auth issue',
        };
    }

    /**
     * @return list<array{reason: string, count: int}>
     */
    private function rootCauseBreakdown(Collection $customers): array
    {
        return $customers
            ->map(fn (Customer $customer): string => $this->downCustomerReason($customer))
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $reason): array => ['reason' => $reason, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Outage>  $activeOutages
     * @return list<array{zone_id: int, area_id: int, area_name: string, zone: string, down_users: int, expired: int, suspended: int, due: int, active_outage: bool}>
     */
    private function zoneImpactSummary(Collection $customers, Collection $activeOutages): array
    {
        $outageAreaIds = $activeOutages->pluck('area_id')->filter()->map(fn ($id) => (int) $id)->all();

        return $customers
            ->groupBy(fn (Customer $customer): string => (string) ($customer->zone?->name ?? 'Unassigned zone'))
            ->map(function (Collection $group, string $zone) use ($outageAreaIds): array {
                $expired = $group->filter(fn (Customer $customer): bool => $customer->isServiceExpired())->count();
                $suspended = $group->where('status', CustomerStatus::SUSPENDED)->count();
                $due = $group->filter(fn (Customer $customer): bool => (bool) ($customer->has_due_invoice ?? false))->count();
                $areaId = (int) ($group->first()?->area_id ?? $group->first()?->zone?->area_id ?? 0);

                return [
                    'zone_id' => (int) ($group->first()?->zone_id ?? 0),
                    'area_id' => $areaId,
                    'area_name' => (string) ($group->first()?->area?->name ?? 'Unassigned area'),
                    'zone' => $zone,
                    'down_users' => $group->count(),
                    'expired' => $expired,
                    'suspended' => $suspended,
                    'due' => $due,
                    'active_outage' => $areaId > 0 && in_array($areaId, $outageAreaIds, true),
                ];
            })
            ->sortByDesc('down_users')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Outage>  $activeOutages
     * @return list<array{area_id: int, area: string, down_users: int, zones: int, active_outage: bool}>
     */
    private function areaImpactSummary(Collection $customers, Collection $activeOutages): array
    {
        $outageAreaIds = $activeOutages->pluck('area_id')->filter()->map(fn ($id) => (int) $id)->all();

        return $customers
            ->groupBy(fn (Customer $customer): string => (string) ($customer->area?->name ?? 'Unassigned area'))
            ->map(function (Collection $group, string $area) use ($outageAreaIds): array {
                $areaId = (int) ($group->first()?->area_id ?? 0);

                return [
                    'area_id' => $areaId,
                    'area' => $area,
                    'down_users' => $group->count(),
                    'zones' => $group->pluck('zone_id')->filter()->unique()->count(),
                    'active_outage' => $areaId > 0 && in_array($areaId, $outageAreaIds, true),
                ];
            })
            ->sortByDesc('down_users')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, label: string, olt_id: int, olt: string, port: string, offline: int, critical: int, total: int, fault_percent: float}>
     */
    private function hotPonPorts(int $tenantId, int $limit = 6): array
    {
        return Cache::remember(
            "dashboard:noc-hot-pon:{$tenantId}:{$limit}",
            now()->addSeconds(60),
            function () use ($tenantId, $limit): array {
                return app(\App\Services\Optical\OpticalSignalHistoryService::class)
                    ->ponPortStats($tenantId)
                    ->sortByDesc(fn ($stat): float => ((float) ($stat->fault_percent ?? 0) * 10) + (int) ($stat->onu_offline ?? 0))
                    ->take($limit)
                    ->map(function ($stat): array {
                        $label = (string) ($stat->oltPort?->label ?? ('C'.(int) ($stat->card_no ?? 0).'/P'.(int) ($stat->pon_no ?? 0)));

                        return [
                            'id' => (string) ($stat->olt_id.'-'.(int) ($stat->card_no ?? 0).'-'.(int) ($stat->pon_no ?? 0)),
                            'olt_id' => (int) ($stat->olt_id ?? 0),
                            'olt' => (string) ($stat->olt?->display_name ?? 'Unknown OLT'),
                            'port' => $label,
                            'offline' => (int) ($stat->onu_offline ?? 0),
                            'critical' => (int) ($stat->onu_critical ?? 0),
                            'total' => (int) ($stat->onu_total ?? 0),
                            'fault_percent' => round((float) ($stat->fault_percent ?? 0), 1),
                        ];
                    })
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $oltRows
     * @return list<array{
     *   id: int,
     *   name: string,
     *   host: string,
     *   reachable: bool,
     *   packet_loss_percent: ?float,
     *   avg_latency_ms: ?float,
     *   sample_count: int,
     *   status: string,
     *   snmp_ok: bool,
     *   temperature_c: ?float,
     *   health_score: ?int,
     *   onus_offline: int,
     *   uptime_human: ?string
     * }>
     */
    private function oltReachabilitySummary(int $tenantId, Collection $oltRows, int $limit = 6): array
    {
        return Cache::remember(
            "dashboard:noc-olt-reachability:{$tenantId}:{$limit}",
            now()->addSeconds(90),
            function () use ($tenantId, $oltRows, $limit): array {
                $targetIds = $oltRows
                    ->sortByDesc(function (array $row): int {
                        $statusPenalty = (string) ($row['status'] ?? '') === 'offline' ? 5 : 0;
                        $linkDown = max(0, (int) ($row['interfaces_total'] ?? 0) - (int) ($row['interfaces_up'] ?? 0));

                        return $statusPenalty
                            + ((int) ($row['onus_offline'] ?? 0) * 2)
                            + $linkDown
                            + ((int) ($row['health_score'] ?? 0) < 60 ? 2 : 0);
                    })
                    ->take($limit)
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($targetIds->isEmpty()) {
                    return [];
                }

                $devices = Device::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $targetIds->all())
                    ->get()
                    ->keyBy('id');

                $probe = app(\App\Services\Olt\OltSnmpProbeService::class);

                return $targetIds->map(function (int $id) use ($devices, $probe, $oltRows): ?array {
                    /** @var Device|null $device */
                    $device = $devices->get($id);
                    $row = (array) ($oltRows->firstWhere('id', $id) ?? []);

                    if ($device === null) {
                        return null;
                    }

                    $ping = $probe->pingSummary($device, 2);

                    return [
                        'id' => $id,
                        'name' => (string) ($row['name'] ?? $device->adminLabel()),
                        'host' => (string) ($ping['host'] ?: ($row['management_ip'] ?? '—')),
                        'reachable' => (bool) $ping['reachable'],
                        'packet_loss_percent' => $ping['packet_loss_percent'] !== null ? (float) $ping['packet_loss_percent'] : null,
                        'avg_latency_ms' => $ping['avg_latency_ms'] !== null ? (float) $ping['avg_latency_ms'] : null,
                        'sample_count' => (int) ($ping['sample_count'] ?? 2),
                        'status' => (string) ($row['status'] ?? 'unknown'),
                        'snmp_ok' => (bool) ($row['snmp_ok'] ?? false),
                        'temperature_c' => isset($row['temperature_c']) && is_numeric($row['temperature_c']) ? round((float) $row['temperature_c'], 1) : null,
                        'health_score' => isset($row['health_score']) ? (int) $row['health_score'] : null,
                        'onus_offline' => (int) ($row['onus_offline'] ?? 0),
                        'uptime_human' => $row['uptime_human'] ?? null,
                    ];
                })->filter()->values()->all();
            },
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $oltRows
     * @return array{
     *   current: array<string, int|float|null|bool>,
     *   trend: array{labels: list<string>, ping_loss_percent: list<float>, pon_module_temp_c: list<float|null>, sfp_temp_c: list<float|null>},
     *   sfp_is_fallback: bool
     * }
     */
    private function accessTelemetry(int $tenantId, Collection $oltRows): array
    {
        return Cache::remember(
            "dashboard:noc-telemetry:{$tenantId}",
            now()->addSeconds(60),
            function () use ($tenantId, $oltRows): array {
                $since = now()->subHours(3);

                $oltLogs = OltHealthLog::query()
                    ->where('tenant_id', $tenantId)
                    ->where('sampled_at', '>=', $since)
                    ->orderBy('sampled_at')
                    ->limit(3000)
                    ->get(['snmp_ok', 'temperature_c', 'sampled_at']);

                $ponLogs = OnuSignalLog::query()
                    ->where('tenant_id', $tenantId)
                    ->where('sampled_at', '>=', $since)
                    ->whereNotNull('temperature_c')
                    ->orderBy('sampled_at')
                    ->limit(4000)
                    ->get(['temperature_c', 'sampled_at']);

                $buckets = [];
                $bucketKey = static function (Carbon $at): string {
                    $minute = (int) floor($at->minute / 10) * 10;

                    return $at->copy()->setMinute($minute)->setSecond(0)->format('Y-m-d H:i');
                };

                foreach ($oltLogs as $log) {
                    $key = $bucketKey($log->sampled_at);
                    $buckets[$key] ??= [
                        'label' => Carbon::parse($key)->format('H:i'),
                        'olt_total' => 0,
                        'olt_bad' => 0,
                        'sfp_sum' => 0.0,
                        'sfp_count' => 0,
                        'pon_sum' => 0.0,
                        'pon_count' => 0,
                    ];

                    $buckets[$key]['olt_total']++;
                    if (! (bool) $log->snmp_ok) {
                        $buckets[$key]['olt_bad']++;
                    }
                    if ($log->temperature_c !== null) {
                        $buckets[$key]['sfp_sum'] += (float) $log->temperature_c;
                        $buckets[$key]['sfp_count']++;
                    }
                }

                foreach ($ponLogs as $log) {
                    $key = $bucketKey($log->sampled_at);
                    $buckets[$key] ??= [
                        'label' => Carbon::parse($key)->format('H:i'),
                        'olt_total' => 0,
                        'olt_bad' => 0,
                        'sfp_sum' => 0.0,
                        'sfp_count' => 0,
                        'pon_sum' => 0.0,
                        'pon_count' => 0,
                    ];

                    $buckets[$key]['pon_sum'] += (float) $log->temperature_c;
                    $buckets[$key]['pon_count']++;
                }

                ksort($buckets);
                $buckets = array_slice($buckets, -12, 12, true);

                $labels = [];
                $pingLossTrend = [];
                $ponTempTrend = [];
                $sfpTempTrend = [];

                foreach ($buckets as $bucket) {
                    $labels[] = $bucket['label'];
                    $pingLossTrend[] = $bucket['olt_total'] > 0
                        ? round(($bucket['olt_bad'] / $bucket['olt_total']) * 100, 1)
                        : 0.0;
                    $ponTempTrend[] = $bucket['pon_count'] > 0
                        ? round($bucket['pon_sum'] / $bucket['pon_count'], 1)
                        : null;
                    $sfpTempTrend[] = $bucket['sfp_count'] > 0
                        ? round($bucket['sfp_sum'] / $bucket['sfp_count'], 1)
                        : null;
                }

                $currentReachabilityBad = $oltRows->filter(function (array $row): bool {
                    return (string) ($row['status'] ?? '') === 'offline'
                        || ! (bool) ($row['snmp_ok'] ?? false);
                })->count();
                $currentReachabilityTotal = max(1, $oltRows->count());

                $sfpTemps = $oltRows
                    ->pluck('temperature_c')
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value): float => round((float) $value, 1))
                    ->values();

                $ponCurrentTemps = $ponLogs
                    ->pluck('temperature_c')
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value): float => round((float) $value, 1))
                    ->values();

                $currentPonAvg = $ponCurrentTemps->isNotEmpty() ? round((float) $ponCurrentTemps->avg(), 1) : null;
                $currentPonMax = $ponCurrentTemps->isNotEmpty() ? round((float) $ponCurrentTemps->max(), 1) : null;
                $currentSfpAvg = $sfpTemps->isNotEmpty() ? round((float) $sfpTemps->avg(), 1) : null;
                $currentSfpMax = $sfpTemps->isNotEmpty() ? round((float) $sfpTemps->max(), 1) : null;
                $currentPingLoss = round(($currentReachabilityBad / $currentReachabilityTotal) * 100, 1);

                if ($labels === []) {
                    $labels = [now()->format('H:i')];
                    $pingLossTrend = [$currentPingLoss];
                    $ponTempTrend = [$currentPonAvg];
                    $sfpTempTrend = [$currentSfpAvg];
                }

                return [
                    'current' => [
                        'ping_loss_percent' => $currentPingLoss,
                        'ping_loss_devices' => $currentReachabilityBad,
                        'olt_reachability_total' => $oltRows->count(),
                        'pon_module_avg_temp_c' => $currentPonAvg,
                        'pon_module_max_temp_c' => $currentPonMax,
                        'sfp_avg_temp_c' => $currentSfpAvg,
                        'sfp_max_temp_c' => $currentSfpMax,
                    ],
                    'trend' => [
                        'labels' => $labels,
                        'ping_loss_percent' => $pingLossTrend,
                        'pon_module_temp_c' => $ponTempTrend,
                        'sfp_temp_c' => $sfpTempTrend,
                    ],
                    'sfp_is_fallback' => true,
                ];
            },
        );
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, array<string, mixed>>  $oltRows
     * @param  Collection<int, Outage>  $activeOutages
     * @param  list<array{id: string, label: string, olt_id: int, olt: string, port: string, offline: int, critical: int, total: int, fault_percent: float}>  $hotPonPorts
     * @return list<array<string, int|string|float|null>>
     */
    private function topImpactRanking(Collection $customers, Collection $oltRows, Collection $activeOutages, array $hotPonPorts): array
    {
        $items = collect();

        foreach (array_slice($this->zoneImpactSummary($customers, $activeOutages), 0, 3) as $zone) {
            $items->push([
                'type' => 'zone',
                'id' => (int) ($zone['zone_id'] ?? 0),
                'label' => (string) ($zone['zone'] ?? 'Zone'),
                'subtext' => (string) ($zone['area_name'] ?? 'Area'),
                'impact' => (int) ($zone['down_users'] ?? 0),
                'detail' => 'Due '.$zone['due'].' · Susp '.$zone['suspended'],
                'score' => (int) ($zone['down_users'] ?? 0),
            ]);
        }

        foreach (array_slice($this->areaImpactSummary($customers, $activeOutages), 0, 2) as $area) {
            $items->push([
                'type' => 'area',
                'id' => (int) ($area['area_id'] ?? 0),
                'label' => (string) ($area['area'] ?? 'Area'),
                'subtext' => (int) ($area['zones'] ?? 0).' impacted zone',
                'impact' => (int) ($area['down_users'] ?? 0),
                'detail' => ((bool) ($area['active_outage'] ?? false)) ? 'Active outage' : 'Heatmap cluster',
                'score' => (int) ($area['down_users'] ?? 0),
            ]);
        }

        foreach ($oltRows
            ->sortByDesc(fn (array $row): int => ((int) ($row['onus_offline'] ?? 0) * 2) + max(0, (int) ($row['interfaces_total'] ?? 0) - (int) ($row['interfaces_up'] ?? 0)))
            ->take(3) as $olt) {
            $linkDown = max(0, (int) ($olt['interfaces_total'] ?? 0) - (int) ($olt['interfaces_up'] ?? 0));
            $items->push([
                'type' => 'olt',
                'id' => (int) ($olt['id'] ?? 0),
                'label' => (string) ($olt['name'] ?? 'OLT'),
                'subtext' => (string) ($olt['status'] ?? 'unknown'),
                'impact' => (int) ($olt['onus_offline'] ?? 0),
                'detail' => 'Offline ONU '.(int) ($olt['onus_offline'] ?? 0).' · Link '.$linkDown,
                'score' => ((int) ($olt['onus_offline'] ?? 0) * 2) + $linkDown,
            ]);
        }

        foreach (array_slice($hotPonPorts, 0, 3) as $port) {
            $items->push([
                'type' => 'pon',
                'id' => (string) ($port['id'] ?? ''),
                'olt_id' => (int) ($port['olt_id'] ?? 0),
                'label' => (string) ($port['port'] ?? 'PON'),
                'subtext' => (string) ($port['olt'] ?? 'OLT'),
                'impact' => (int) ($port['offline'] ?? 0),
                'detail' => 'Fault '.(float) ($port['fault_percent'] ?? 0).'% · Critical '.(int) ($port['critical'] ?? 0),
                'score' => ((int) ($port['offline'] ?? 0) * 2) + (int) round((float) ($port['fault_percent'] ?? 0) / 10),
            ]);
        }

        return $items
            ->sortByDesc('score')
            ->take(8)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'id' => $item['id'],
                'olt_id' => $item['olt_id'] ?? null,
                'label' => $item['label'],
                'subtext' => $item['subtext'],
                'impact' => $item['impact'],
                'detail' => $item['detail'],
            ])
            ->values()
            ->all();
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
