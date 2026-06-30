<?php

namespace App\Services\Ai;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\InventoryHub;
use App\Filament\Pages\InventoryWarrantyManagement;
use App\Filament\Pages\IspOsHub;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\PaymentsReport;
use App\Filament\Pages\ReportsHub;
use App\Filament\Resources\SupportTicketResource;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Zone;
use App\Services\Dashboard\AiAnalyticsService;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Hr\WorkforceHubDashboardService;
use App\Services\Inventory\InventoryAssetIntelligenceService;
use App\Services\IspOs\IspOsIntelligenceService;
use App\Services\IspOs\OperationalInsightsService;
use App\Services\IspOs\RootCauseAnalysisService;
use App\Services\Optical\OpticalDashboardService;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\Rbac\StaffCapability;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

/**
 * Read-only AI operations orchestrator — advisory only, no mutations.
 */
final class AiOperationsOrchestrator
{
    public function __construct(
        private readonly AiIntentCatalog $intents,
        private readonly AiInsightComposer $composer,
        private readonly AiAlertAggregator $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'ai_copilot:dashboard:'.$tenantId,
            now()->addSeconds(120),
            fn (): array => [
                'summary' => $this->executiveSummary($tenantId),
                'alerts' => $this->alerts->alerts($tenantId),
                'recommendations' => app(AiAnalyticsService::class)->insights($tenantId)['recommendations'] ?? [],
                'chips' => array_slice($this->intents->quickChips(), 0, 12),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $sessionState
     * @return array<string, mixed>
     */
    public function ask(string $query, array $sessionState = [], ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $settings = app(AiSettingsService::class);
        abort_unless($settings->isEnabled($tenantId), 503, 'AI Copilot is disabled for this tenant.');
        abort_unless($settings->withinDailyQuota($tenantId), 429, 'Daily AI query limit reached.');

        $started = microtime(true);
        $context = new AiSessionContext;
        $context->hydrate($sessionState);
        $llmUsed = false;

        $followUp = $this->intents->parseFollowUpFilters($query);
        if (! empty($followUp['_clear'])) {
            $context->clearFilters();
            unset($followUp['_clear']);
        }
        if ($followUp !== []) {
            $context->mergeFilters($followUp);
        }

        $tool = $this->intents->resolve($query);
        $lastTool = (string) ($sessionState['last_tool'] ?? '');

        if ($tool === null) {
            $tool = app(AiLlmGateway::class)->resolveTool($query, $tenantId);
            $llmUsed = $tool !== null;
        }

        if ($tool === null && $followUp !== [] && $lastTool !== '') {
            $tool = $lastTool;
        }

        $bn = $settings->bengaliReplies($tenantId) && preg_match('/[\x{0980}-\x{09FF}]/u', $query);

        if ($tool === null) {
            $rag = app(AiRagService::class)->contextBlock($query, $tenantId);
            $reply = $bn
                ? 'আমি বিলিং, NOC, টিকেট, ইনভেন্টরি, HR ও GIS-এ সাহায্য করতে পারি। চেষ্টা করুন: "বকেয়া কাস্টমার", "অফলাইন ONU", "অপারেশন সারাংশ"।'
                : 'I can help with billing, NOC, tickets, inventory, HR, and GIS. Try: "Show offline ONUs", "Show due customers", or "Operational summary".';
            if ($rag !== '') {
                $reply .= "\n\n".$rag;
            }
            $context->addMessage('user', $query);
            $context->addMessage('assistant', $reply);
            $this->logInteraction($query, $reply, null, 'general', $llmUsed, $started);

            return [
                'reply' => $reply,
                'cards' => [],
                'table' => null,
                'links' => [['label' => 'AI recommendations', 'url' => \App\Filament\Pages\AiOperationsCopilotHub::getUrl()]],
                'session' => array_merge($context->toArray(), ['last_tool' => null]),
                'advisory' => true,
            ];
        }

        $user = auth()->user();
        if ($user instanceof User) {
            app(AiToolPermissionGuard::class)->assertCanRunTool($tool, $user);
        }
        abort_unless($settings->toolAllowed($tool, $tenantId), 403, 'This AI tool is disabled.');

        $payload = $this->runToolCached($tool, $context->filters(), $tenantId);
        $composed = $this->composer->compose($tool, $payload);

        $summary = app(AiLlmGateway::class)->summarizeToolResult($tool, $query, $payload, $tenantId);
        if (is_string($summary) && $summary !== '') {
            $composed['reply'] = $summary;
            $llmUsed = true;
        }

        $context->addMessage('user', $query);
        $context->addMessage('assistant', $composed['reply']);
        $this->logInteraction($query, $composed['reply'], $tool, (string) ($composed['domain'] ?? 'general'), $llmUsed, $started);

        return array_merge($composed, [
            'session' => array_merge($context->toArray(), ['last_tool' => $tool]),
            'advisory' => true,
        ]);
    }

    private function logInteraction(string $query, string $reply, ?string $tool, string $domain, bool $llmUsed, float $started): void
    {
        $actor = auth()->user();
        app(AiAuditLogger::class)->log(
            channel: 'staff',
            query: $query,
            reply: $reply,
            tool: $tool,
            domain: $domain,
            llmUsed: $llmUsed,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            actor: is_object($actor) ? $actor : null,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function runToolCached(string $tool, array $filters, int $tenantId): array
    {
        $filterKey = md5(json_encode($filters) ?: '');

        return SafeCache::remember(
            'ai_copilot:tool:'.$tenantId.':'.$tool.':'.$filterKey,
            now()->addSeconds(90),
            fn (): array => $this->runTool($tool, $filters, $tenantId),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function runTool(string $tool, array $filters, int $tenantId): array
    {
        return match ($tool) {
            'billing.due_customers' => $this->toolDueCustomers($filters, $tenantId),
            'billing.today_collection' => $this->toolTodayCollection($tenantId),
            'billing.monthly_revenue' => $this->toolMonthlyRevenue($tenantId),
            'billing.revenue_by_zone' => $this->toolRevenueByZone($filters, $tenantId),
            'billing.top_packages' => $this->toolTopPackages($tenantId),
            'network.offline_onus' => $this->toolOfflineOnus($filters, $tenantId),
            'network.offline_routers' => $this->toolOfflineRouters($tenantId),
            'network.weak_signals' => $this->toolWeakSignals($tenantId),
            'network.olt_capacity' => $this->toolOltCapacity($tenantId),
            'network.rca' => $this->toolRca($tenantId),
            'support.open_tickets' => $this->toolOpenTickets($filters, $tenantId),
            'support.complaint_trends' => $this->toolComplaintTrends($tenantId),
            'support.ticket_triage' => $this->toolTicketTriage($tenantId),
            'gis.complaint_density' => $this->toolComplaintDensity($tenantId),
            'inventory.low_stock' => $this->toolLowStock($tenantId),
            'inventory.warranty_expiring' => $this->toolWarrantyExpiring($tenantId),
            'hr.technician_performance' => $this->toolTechnicianPerformance($tenantId),
            'hr.attendance' => $this->toolAttendance($tenantId),
            'bi.recommendations' => $this->toolRecommendations($tenantId),
            'bi.churn' => $this->toolChurn($tenantId),
            'bi.churn_scored' => $this->toolChurnScored($tenantId),
            'bi.summary' => $this->toolExecutiveSummary($tenantId),
            'actions.propose_suspend_defaulters' => $this->toolProposeSuspendDefaulters($tenantId),
            default => [
                'summary' => 'Tool not available.',
                'domain' => 'general',
                'cards' => [],
                'links' => [],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function executiveSummary(int $tenantId): array
    {
        return SafeCache::remember(
            'ai_copilot:exec_summary:'.$tenantId,
            now()->addSeconds(120),
            fn (): array => $this->buildExecutiveSummary($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExecutiveSummary(int $tenantId): array
    {
        $isp = app(IspOsIntelligenceService::class)->payload($tenantId);
        $ai = app(AiAnalyticsService::class)->insights($tenantId);
        $snap = app(DashboardMetricsService::class)->snapshot($tenantId);

        return [
            'revenue_trend_pct' => $ai['revenue_trend_pct'] ?? 0,
            'collected_today' => $snap['collected_today'] ?? 0,
            'open_tickets' => $isp['open_tickets'] ?? 0,
            'customers_offline' => $isp['customers_offline'] ?? 0,
            'active_faults' => $isp['active_faults'] ?? 0,
            'network_health' => $isp['network_health_score'] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function toolDueCustomers(array $filters, int $tenantId): array
    {
        $this->guardBilling();

        $query = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereHas('invoices', fn ($q) => $q
                ->whereIn('status', ['open', 'partial'])
                ->whereRaw('(total - amount_paid) > 0'));

        $this->applyAreaZoneFilter($query, $filters, $tenantId);

        $customers = $query->with('area:id,name')->limit(25)->get(['id', 'name', 'customer_code', 'phone', 'area_id']);
        $rows = $customers->map(fn (Customer $c): array => [
            $c->name,
            (string) $c->customer_code,
            number_format($c->openInvoiceBalance(), 0).' BDT',
            $c->area?->name ?? '—',
        ])->all();

        $count = count($rows);
        $filterNote = $this->filterNote($filters);

        return [
            'domain' => 'billing',
            'summary' => "{$count} active subscribers with open balances{$filterNote}. Advisory only — use collection desk to act.",
            'table' => $this->composer->table(['Customer', 'ID', 'Due', 'Area'], $rows),
            'links' => [
                ['label' => 'Due report', 'url' => \App\Filament\Pages\DueReportPage::getUrl()],
                ['label' => 'Collection desk', 'url' => BillCollectionDesk::getUrl()],
            ],
        ];
    }

    private function toolTodayCollection(int $tenantId): array
    {
        $this->guardBilling();
        $snap = app(DashboardMetricsService::class)->snapshot($tenantId);
        $amount = (float) ($snap['collected_today'] ?? 0);

        return [
            'domain' => 'billing',
            'summary' => "Today's collection is ".number_format($amount, 0).' BDT.',
            'cards' => [
                ['title' => 'Collected today', 'value' => number_format($amount, 0).' BDT', 'tone' => 'emerald'],
            ],
            'links' => [
                ['label' => 'Payments report', 'url' => PaymentsReport::getUrl()],
                ['label' => 'Collection desk', 'url' => BillCollectionDesk::getUrl()],
            ],
        ];
    }

    private function toolMonthlyRevenue(int $tenantId): array
    {
        $this->guardBilling();
        $ai = app(AiAnalyticsService::class)->insights($tenantId);
        $trend = (float) ($ai['revenue_trend_pct'] ?? 0);
        $forecast = (float) ($ai['revenue_forecast_mtd'] ?? 0);
        $dir = $trend >= 0 ? 'up' : 'down';

        return [
            'domain' => 'billing',
            'summary' => "Month-to-date revenue forecast is ".number_format($forecast, 0)." BDT — {$dir} ".abs($trend).'% vs last month.',
            'cards' => [
                ['title' => 'MTD forecast', 'value' => number_format($forecast, 0).' BDT', 'tone' => 'indigo'],
                ['title' => 'Trend', 'value' => ($trend >= 0 ? '+' : '').$trend.'%', 'tone' => $trend >= 0 ? 'emerald' : 'rose'],
            ],
            'links' => [['label' => 'Reports hub', 'url' => ReportsHub::getUrl()]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function toolRevenueByZone(array $filters, int $tenantId): array
    {
        $this->guardBilling();

        $rows = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('payment_type', PaymentType::PAYMENT)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->join('customers', 'customers.id', '=', 'payments.customer_id')
            ->leftJoin('zones', 'zones.id', '=', 'customers.zone_id')
            ->selectRaw('COALESCE(zones.name, ?) as zone_name, SUM(payments.amount) as total', ['Unzoned'])
            ->groupBy('zones.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r): array => [(string) $r->zone_name, number_format((float) $r->total, 0).' BDT'])
            ->all();

        return [
            'domain' => 'billing',
            'summary' => 'Revenue by zone this month (top 10).',
            'table' => $this->composer->table(['Zone', 'Collected'], $rows),
            'links' => [['label' => 'Payments report', 'url' => PaymentsReport::getUrl()]],
        ];
    }

    private function toolTopPackages(int $tenantId): array
    {
        $this->guardBilling();

        $rows = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereNotNull('package_id')
            ->join('packages', 'packages.id', '=', 'customers.package_id')
            ->selectRaw('packages.name, COUNT(*) as subs')
            ->groupBy('packages.name')
            ->orderByDesc('subs')
            ->limit(10)
            ->get()
            ->map(fn ($r): array => [(string) $r->name, (string) $r->subs])
            ->all();

        return [
            'domain' => 'billing',
            'summary' => 'Top packages by active subscriber count.',
            'table' => $this->composer->table(['Package', 'Subscribers'], $rows),
            'links' => [['label' => 'Package report', 'url' => \App\Filament\Pages\PackageWiseReportPage::getUrl()]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function toolOfflineOnus(array $filters, int $tenantId): array
    {
        $this->guardNetwork();
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);
        $offline = (int) ($optical['offline_onus'] ?? 0);

        $query = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->where(function ($q): void {
                $q->where('onu_oper_status', 'offline')
                    ->orWhereIn('onu_oper_status', ['los', 'power_fail', 'auth_fail']);
            });

        if (filled($filters['area'] ?? null)) {
            $areaId = Area::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('name', 'like', '%'.$filters['area'].'%')->value('id');
            if ($areaId) {
                $query->whereHas('customer', fn ($c) => $c->where('area_id', $areaId));
            }
        }

        $devices = $query->with('customer:id,name,customer_code')->limit(20)->get();
        $rows = $devices->map(fn (Device $d): array => [
            $d->serial_number,
            (string) ($d->onu_oper_status ?? 'unknown'),
            $d->customer?->name ?? '—',
        ])->all();

        $filterNote = $this->filterNote($filters);

        return [
            'domain' => 'noc',
            'summary' => "{$offline} offline ONUs network-wide{$filterNote}. Showing up to 20.",
            'table' => $this->composer->table(['Serial', 'Status', 'Customer'], $rows),
            'links' => [['label' => 'Optical NOC', 'url' => OpticalMonitoringHub::getUrl()]],
        ];
    }

    private function toolOfflineRouters(int $tenantId): array
    {
        $this->guardNetwork();
        $isp = app(IspOsIntelligenceService::class)->payload($tenantId);
        $offline = max(0, (int) ($isp['routers_total'] ?? 0) - (int) ($isp['routers_online'] ?? 0));

        return [
            'domain' => 'noc',
            'summary' => "Approximately {$offline} router(s) appear offline.",
            'cards' => [
                ['title' => 'Routers online', 'value' => (string) ($isp['routers_online'] ?? 0), 'tone' => 'emerald'],
                ['title' => 'Routers total', 'value' => (string) ($isp['routers_total'] ?? 0), 'tone' => 'sky'],
            ],
            'links' => [['label' => 'Network intelligence', 'url' => \App\Filament\Pages\NetworkIntelligenceHub::getUrl()]],
        ];
    }

    private function toolWeakSignals(int $tenantId): array
    {
        $this->guardNetwork();
        $optical = app(OpticalDashboardService::class)->snapshot($tenantId);

        return [
            'domain' => 'noc',
            'summary' => (int) ($optical['critical_onus'] ?? 0).' critical and '.(int) ($optical['warning_onus'] ?? 0).' warning ONU signal(s) detected.',
            'cards' => [
                ['title' => 'Critical', 'value' => (string) ($optical['critical_onus'] ?? 0), 'tone' => 'rose'],
                ['title' => 'Warning', 'value' => (string) ($optical['warning_onus'] ?? 0), 'tone' => 'amber'],
            ],
            'links' => [['label' => 'Optical NOC', 'url' => OpticalMonitoringHub::getUrl()]],
        ];
    }

    private function toolOltCapacity(int $tenantId): array
    {
        $this->guardNetwork();
        $insights = app(OperationalInsightsService::class)->forTenant($tenantId);
        $cards = array_map(fn (array $i): array => [
            'title' => 'Capacity warning',
            'value' => \Illuminate\Support\Str::limit((string) ($i['message'] ?? ''), 60),
            'tone' => ($i['tone'] ?? '') === 'critical' ? 'rose' : 'amber',
        ], $insights);

        return [
            'domain' => 'noc',
            'summary' => count($insights) > 0
                ? count($insights).' PON/OLT capacity insight(s) require review.'
                : 'No high PON utilization warnings right now.',
            'cards' => array_slice($cards, 0, 5),
            'links' => [['label' => 'OLT hub', 'url' => \App\Filament\Pages\OltHub::getUrl()]],
        ];
    }

    private function toolRca(int $tenantId): array
    {
        $this->guardNetwork();
        $items = app(RootCauseAnalysisService::class)->analyze($tenantId);
        $cards = array_map(fn (array $r): array => [
            'title' => str_replace('_', ' ', (string) ($r['root_cause'] ?? 'unknown')),
            'value' => \Illuminate\Support\Str::limit((string) ($r['message'] ?? ''), 72),
            'tone' => ($r['tone'] ?? '') === 'critical' ? 'rose' : 'amber',
            'hint' => round(((float) ($r['confidence'] ?? 0)) * 100).'% confidence',
        ], $items);

        return [
            'domain' => 'noc',
            'summary' => count($items) > 0
                ? 'Root cause analysis — '.count($items).' active pattern(s). Advisory only.'
                : 'No major root-cause patterns detected.',
            'cards' => $cards,
            'links' => [['label' => 'Fault center', 'url' => \App\Filament\Pages\FaultManagementHub::getUrl()]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function toolOpenTickets(array $filters, int $tenantId): array
    {
        $this->guardSupport();

        $query = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed']);

        if (($filters['severity'] ?? '') === 'critical') {
            $query->whereIn('priority', ['critical', 'high']);
        }

        $this->applyAreaZoneFilterOnTicket($query, $filters, $tenantId);

        $tickets = $query->orderByDesc('updated_at')->limit(20)->get(['ticket_number', 'subject', 'priority', 'status']);
        $rows = $tickets->map(fn (SupportTicket $t): array => [
            '#'.$t->ticket_number,
            \Illuminate\Support\Str::limit((string) $t->subject, 40),
            (string) $t->priority,
            (string) $t->status,
        ])->all();

        return [
            'domain' => 'support',
            'summary' => count($rows).' open ticket(s)'.$this->filterNote($filters).'.',
            'table' => $this->composer->table(['Ticket', 'Subject', 'Priority', 'Status'], $rows),
            'links' => [['label' => 'Support tickets', 'url' => SupportTicketResource::getUrl()]],
        ];
    }

    private function toolComplaintTrends(int $tenantId): array
    {
        $this->guardSupport();

        $thisWeek = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        $lastWeek = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();
        $pct = $lastWeek > 0 ? round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1) : 0;

        $topIssue = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->select('issue_type', DB::raw('COUNT(*) as c'))
            ->groupBy('issue_type')
            ->orderByDesc('c')
            ->first();

        $issue = $topIssue?->issue_type ? str_replace('_', ' ', (string) $topIssue->issue_type) : 'connection';

        return [
            'domain' => 'support',
            'summary' => "Ticket volume this week: {$thisWeek} (".($pct >= 0 ? '+' : '')."{$pct}% vs last week). Most common issue: {$issue}.",
            'cards' => [
                ['title' => 'This week', 'value' => (string) $thisWeek, 'tone' => 'cyan'],
                ['title' => 'Top issue (30d)', 'value' => ucfirst($issue), 'tone' => 'amber'],
            ],
            'links' => [['label' => 'Support hub', 'url' => \App\Filament\Pages\SupportHub::getUrl()]],
        ];
    }

    private function toolComplaintDensity(int $tenantId): array
    {
        $this->guardSupport();

        $rows = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->join('customers', 'customers.id', '=', 'support_tickets.customer_id')
            ->leftJoin('areas', 'areas.id', '=', 'customers.area_id')
            ->selectRaw('COALESCE(areas.name, ?) as area_name, COUNT(*) as c', ['Unknown'])
            ->groupBy('areas.name')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($r): array => [(string) $r->area_name, (string) $r->c])
            ->all();

        $top = $rows[0][0] ?? '—';

        return [
            'domain' => 'gis',
            'summary' => "Highest complaint volume in the last 30 days: {$top}.",
            'table' => $this->composer->table(['Area', 'Tickets'], $rows),
            'links' => [['label' => 'GIS map', 'url' => \App\Filament\Pages\FiberPlantMap::getUrl()]],
        ];
    }

    private function toolLowStock(int $tenantId): array
    {
        $this->guardInventory();
        $m = app(InventoryAssetIntelligenceService::class)->metrics($tenantId);

        return [
            'domain' => 'inventory',
            'summary' => (int) ($m['low_stock_count'] ?? 0).' SKU(s) at or below reorder level. Stock value: '.number_format((float) ($m['stock_value'] ?? 0), 0).' BDT.',
            'cards' => [
                ['title' => 'Low stock SKUs', 'value' => (string) ($m['low_stock_count'] ?? 0), 'tone' => 'amber'],
                ['title' => 'Stock units', 'value' => number_format((int) ($m['stock_units'] ?? 0)), 'tone' => 'cyan'],
            ],
            'links' => [['label' => 'Inventory hub', 'url' => InventoryHub::getUrl()]],
        ];
    }

    private function toolWarrantyExpiring(int $tenantId): array
    {
        $this->guardInventory();
        $m = app(InventoryAssetIntelligenceService::class)->metrics($tenantId);
        $count = (int) ($m['warranty_expiring'] ?? 0);

        return [
            'domain' => 'inventory',
            'summary' => "{$count} device warranty(ies) expire within 30 days.",
            'cards' => [['title' => 'Expiring soon', 'value' => (string) $count, 'tone' => 'rose']],
            'links' => [['label' => 'Warranty desk', 'url' => InventoryWarrantyManagement::getUrl()]],
        ];
    }

    private function toolTechnicianPerformance(int $tenantId): array
    {
        $this->guardHr();
        $weekStart = now()->startOfWeek();

        $rows = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', $weekStart)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as c')
            ->groupBy('assigned_to')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(function ($r) use ($tenantId): array {
                $name = User::query()->find($r->assigned_to)?->name ?? 'Staff #'.$r->assigned_to;

                return [$name, (string) $r->c];
            })
            ->all();

        return [
            'domain' => 'hr',
            'summary' => 'Technician ticket closures this week (top performers).',
            'table' => $this->composer->table(['Technician', 'Resolved'], $rows),
            'links' => [['label' => 'Workforce hub', 'url' => \App\Filament\Pages\HrPayrollHub::getUrl()]],
        ];
    }

    private function toolAttendance(int $tenantId): array
    {
        $this->guardHr();
        $hr = app(WorkforceHubDashboardService::class)->snapshot($tenantId);
        $kpis = $hr['kpis'] ?? [];

        return [
            'domain' => 'hr',
            'summary' => 'Attendance today: '.(int) ($kpis['present_today'] ?? 0).' present, '.(int) ($kpis['absent_today'] ?? 0).' absent, '.(int) ($kpis['leave_today'] ?? 0).' on leave.',
            'cards' => [
                ['title' => 'Present', 'value' => (string) ($kpis['present_today'] ?? 0), 'tone' => 'emerald'],
                ['title' => 'Absent', 'value' => (string) ($kpis['absent_today'] ?? 0), 'tone' => 'rose'],
            ],
            'links' => [['label' => 'HR hub', 'url' => \App\Filament\Pages\HrPayrollHub::getUrl()]],
        ];
    }

    private function toolRecommendations(int $tenantId): array
    {
        $ai = app(AiAnalyticsService::class)->insights($tenantId);
        $cards = array_map(fn (array $r): array => [
            'title' => ucfirst((string) ($r['priority'] ?? 'medium')).' priority',
            'value' => (string) ($r['text'] ?? ''),
            'tone' => ($r['priority'] ?? '') === 'high' ? 'rose' : 'amber',
        ], $ai['recommendations'] ?? []);

        return [
            'domain' => 'bi',
            'summary' => count($cards).' advisory recommendation(s). No automatic actions will be taken.',
            'cards' => $cards,
            'links' => [['label' => 'Reports hub', 'url' => ReportsHub::getUrl()]],
        ];
    }

    private function toolChurn(int $tenantId): array
    {
        $this->guardBilling();
        $ai = app(AiAnalyticsService::class)->insights($tenantId);

        return [
            'domain' => 'bi',
            'summary' => (int) ($ai['churn_risk_customers'] ?? 0).' subscriber(s) at churn risk (expiry or balance due).',
            'cards' => [
                ['title' => 'Churn risk', 'value' => (string) ($ai['churn_risk_customers'] ?? 0), 'tone' => 'rose'],
                ['title' => 'Payment risk', 'value' => (string) ($ai['payment_risk_invoices'] ?? 0), 'tone' => 'amber'],
            ],
            'links' => [['label' => 'Churn reports', 'url' => \App\Filament\Pages\ChurnZoneReports::getUrl()]],
        ];
    }

    private function toolChurnScored(int $tenantId): array
    {
        $this->guardBilling();
        $rows = app(AiChurnScoringService::class)->scoredCustomers($tenantId, 15);
        $tableRows = array_map(
            fn (array $r): array => [
                (string) ($r['customer_code'] ?? $r['customer_id']),
                (string) $r['name'],
                (string) $r['score'],
                (string) $r['risk'],
            ],
            $rows,
        );

        return [
            'domain' => 'bi',
            'summary' => count($rows).' subscriber(s) with elevated churn score (40+).',
            'table' => $this->composer->table(['Code', 'Name', 'Score', 'Risk'], $tableRows),
            'links' => [['label' => 'Churn reports', 'url' => \App\Filament\Pages\ChurnZoneReports::getUrl()]],
        ];
    }

    private function toolTicketTriage(int $tenantId): array
    {
        $this->guardSupport();
        $ticket = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('id')
            ->first();

        if ($ticket === null) {
            return [
                'domain' => 'support',
                'summary' => 'No open tickets to triage.',
                'cards' => [],
                'links' => [['label' => 'Support hub', 'url' => \App\Filament\Pages\SupportHub::getUrl()]],
            ];
        }

        $triage = app(AiTicketTriageService::class)->triageTicket($ticket);

        return [
            'domain' => 'support',
            'summary' => "Ticket #{$ticket->ticket_number}: suggest {$triage['priority']} priority, {$triage['issue_type']}, dept {$triage['department']}.",
            'cards' => [
                ['title' => 'Priority', 'value' => (string) $triage['priority'], 'tone' => 'amber'],
                ['title' => 'Issue', 'value' => (string) $triage['issue_type'], 'tone' => 'cyan'],
            ],
            'table' => $this->composer->table(
                ['Field', 'Suggestion'],
                [
                    ['Assignee', (string) data_get($triage, 'suggested_assignee.name', '—')],
                    ['Reply (BN)', (string) $triage['suggested_reply_bn']],
                ],
            ),
            'links' => [['label' => 'Open ticket', 'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket])]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolProposeSuspendDefaulters(int $tenantId): array
    {
        $this->guardBilling();
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $result = app(AiActionApprovalService::class)->proposeSuspendChronicDefaulters($tenantId, $user);

        return [
            'domain' => 'actions',
            'summary' => (string) $result['summary'],
            'cards' => [
                ['title' => 'Pending approval', 'value' => (string) count($result['preview']['customers'] ?? []), 'tone' => 'rose'],
            ],
            'links' => [['label' => 'AI action queue', 'url' => \App\Filament\Pages\AiActionApprovalHub::getUrl()]],
            'action_request_id' => $result['action_request_id'] ?? null,
        ];
    }

    private function toolExecutiveSummary(int $tenantId): array
    {
        $s = $this->executiveSummary($tenantId);

        return [
            'domain' => 'bi',
            'summary' => 'Operational summary: '.number_format((float) ($s['collected_today'] ?? 0), 0).' BDT collected today · '.(int) ($s['open_tickets'] ?? 0).' open tickets · '.(int) ($s['customers_offline'] ?? 0).' subscribers offline · network health '.(int) ($s['network_health'] ?? 0).'/100.',
            'cards' => [
                ['title' => 'Collected today', 'value' => number_format((float) ($s['collected_today'] ?? 0), 0).' BDT', 'tone' => 'emerald'],
                ['title' => 'Open tickets', 'value' => (string) ($s['open_tickets'] ?? 0), 'tone' => 'cyan'],
                ['title' => 'Offline subs', 'value' => (string) ($s['customers_offline'] ?? 0), 'tone' => 'amber'],
                ['title' => 'Active faults', 'value' => (string) ($s['active_faults'] ?? 0), 'tone' => 'rose'],
            ],
            'links' => [['label' => 'ISP OS', 'url' => IspOsHub::getUrl()]],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyAreaZoneFilter($query, array $filters, int $tenantId): void
    {
        if (filled($filters['area'] ?? null)) {
            $areaId = Area::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('name', 'like', '%'.$filters['area'].'%')->value('id');
            if ($areaId) {
                $query->where('area_id', $areaId);
            }
        }
        if (filled($filters['zone'] ?? null)) {
            $zoneId = Zone::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('name', 'like', '%'.$filters['zone'].'%')->value('id');
            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SupportTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyAreaZoneFilterOnTicket($query, array $filters, int $tenantId): void
    {
        if (filled($filters['area'] ?? null) || filled($filters['zone'] ?? null)) {
            $query->whereHas('customer', function ($c) use ($filters, $tenantId): void {
                if (filled($filters['area'] ?? null)) {
                    $areaId = Area::withoutGlobalScopes()->where('tenant_id', $tenantId)
                        ->where('name', 'like', '%'.$filters['area'].'%')->value('id');
                    if ($areaId) {
                        $c->where('area_id', $areaId);
                    }
                }
                if (filled($filters['zone'] ?? null)) {
                    $zoneId = Zone::withoutGlobalScopes()->where('tenant_id', $tenantId)
                        ->where('name', 'like', '%'.$filters['zone'].'%')->value('id');
                    if ($zoneId) {
                        $c->where('zone_id', $zoneId);
                    }
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterNote(array $filters): string
    {
        $parts = [];
        if (filled($filters['area'] ?? null)) {
            $parts[] = 'area '.$filters['area'];
        }
        if (filled($filters['zone'] ?? null)) {
            $parts[] = 'zone '.$filters['zone'];
        }

        return $parts !== [] ? ' ('.implode(', ', $parts).')' : '';
    }

    private function guardBilling(): void
    {
        abort_unless(StaffCapability::for(auth()->user())->canBilling() || StaffCapability::for(auth()->user())->canReports(), 403);
    }

    private function guardNetwork(): void
    {
        abort_unless(StaffCapability::for(auth()->user())->canNetwork() || StaffCapability::for(auth()->user())->canReports(), 403);
    }

    private function guardSupport(): void
    {
        abort_unless(StaffCapability::for(auth()->user())->canSupport() || StaffCapability::for(auth()->user())->canReports(), 403);
    }

    private function guardInventory(): void
    {
        abort_unless(StaffCapability::for(auth()->user())->canInventory() || StaffCapability::for(auth()->user())->canReports(), 403);
    }

    private function guardHr(): void
    {
        abort_unless(StaffCapability::for(auth()->user())->canHrm() || StaffCapability::for(auth()->user())->canReports(), 403);
    }
}
