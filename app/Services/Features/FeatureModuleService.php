<?php

namespace App\Services\Features;

use App\Models\AutomaticProcess;
use App\Models\BillingInfo;
use App\Models\CallDeskLog;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HrAttendanceLog;
use App\Models\HrLeaveRequest;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySale;
use App\Models\InventoryStockMovement;
use App\Models\InventoryWarehouse;
use App\Models\IspExpense;
use App\Models\MikrotikLog;
use App\Models\NotificationLogs;
use App\Models\Olt;
use App\Models\OltHealthLog;
use App\Models\PackagePurchaseRequest;
use App\Models\PPPSecrets;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\RouterList;
use App\Models\SmsTemplate;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Voucher;
use App\Support\FeatureModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class FeatureModuleService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(string $slug): array
    {
        $module = FeatureModuleRegistry::find($slug);
        if ($module === null) {
            abort(404);
        }

        return match ($slug) {
            'staff-performance' => $this->staffPerformance($module),
            'bill-money-trail' => $this->billMoneyTrail($module),
            'payment-report', 'bkash-collections' => $this->paymentReport($module, $slug === 'bkash-collections'),
            'payment-center' => $this->paymentCenter($module),
            'late-fees' => $this->lateFees($module),
            'coupons', 'offers' => $this->promotions($module, $slug),
            'sms-report' => $this->smsReport($module),
            'comms-hub' => $this->commsHub($module),
            'whatsapp-bot' => $this->whatsappBot($module),
            'noc-wall' => $this->nocWall($module),
            'fault-management' => $this->faultManagement($module),
            'snmp-monitor' => $this->snmpMonitor($module),
            'netflow-analysis' => $this->netflowAnalysis($module),
            'network-topology' => $this->networkTopology($module),
            'fiber-plant-map' => $this->fiberPlantMap($module),
            'pop-boxes' => $this->popBoxes($module),
            'session-integrity' => $this->sessionIntegrity($module),
            'call-reports' => $this->callReports($module),
            'new-connections' => $this->newConnections($module),
            'sales-pipeline' => $this->salesPipeline($module),
            'task-board' => $this->taskBoard($module),
            'field-technicians' => $this->fieldTechnicians($module),
            'payroll-runs' => $this->payrollRuns($module),
            'warehouses' => $this->warehouses($module),
            'pos-sale', 'retail-sales' => $this->posSales($module, $slug),
            'stock-ledger' => $this->stockLedger($module),
            'vendors' => $this->vendors($module),
            'reports-center' => $this->reportsCenter($module),
            'api-configuration' => $this->apiConfiguration($module),
            'performance-settings' => $this->performanceSettings($module),
            'queue-monitor' => $this->queueMonitor($module),
            'mobile-apps' => $this->mobileApps($module),
            'ai-copilot' => $this->aiCopilot($module),
            default => $this->genericWorkspace($module),
        };
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function base(array $module): array
    {
        return [
            'module' => $module,
            'title' => $module['label'],
            'description' => $module['description'],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function genericWorkspace(array $module): array
    {
        $related = array_values(array_filter(
            FeatureModuleRegistry::forGroup($module['group']),
            fn (array $m): bool => $m['slug'] !== $module['slug'],
        ));

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Group'), 'value' => $module['group'], 'color' => 'dark'],
                ['label' => __('Modules'), 'value' => (string) count(FeatureModuleRegistry::forGroup($module['group'])), 'color' => 'primary'],
            ],
            'actions' => array_map(fn (array $m) => [
                'label' => $m['label'],
                'url' => FeatureModuleRegistry::url($m),
                'class' => 'btn-outline-primary',
            ], array_slice($related, 0, 6)),
            'columns' => [],
            'rows' => [],
            'notice' => __('This workspace is linked to the anetbd module catalog. Use the actions below or ISP OS Center for full navigation.'),
        ]);
    }

    /** @param array<string, mixed> $module */
    private function staffPerformance(array $module): array
    {
        $from = now()->startOfMonth();
        $rows = CollectionSummary::query()
            ->whereBetween('collection_date', [$from->toDateString(), now()->toDateString()])
            ->selectRaw('COALESCE(NULLIF(collected_by,""),"Unknown") as staff, SUM(collection_amount) as total, COUNT(*) as cnt')
            ->groupBy('staff')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'staff' => (string) $r->staff,
                'total' => number_format((float) $r->total, 2),
                'count' => (int) $r->cnt,
            ])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Staff with collections'), 'value' => (string) count($rows), 'color' => 'primary'],
                ['label' => __('Month total'), 'value' => '৳'.number_format((float) CollectionSummary::whereBetween('collection_date', [$from, now()])->sum('collection_amount'), 0), 'color' => 'success'],
            ],
            'columns' => [
                ['key' => 'staff', 'label' => __('Staff')],
                ['key' => 'total', 'label' => __('Collected')],
                ['key' => 'count', 'label' => __('Receipts')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Collection report'), 'url' => route('collection-report.index'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function billMoneyTrail(array $module): array
    {
        $month = now()->startOfMonth();
        $collections = (float) CollectionSummary::where('collection_date', '>=', $month)->sum('collection_amount');
        $expenses = (float) IspExpense::where('expense_date', '>=', $month)->sum('amount');
        $commissions = (float) ResellerCommission::where('created_at', '>=', $month)->sum('amount');
        $dues = (float) BillingInfo::sum('due_amount');

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Collections MTD'), 'value' => '৳'.number_format($collections, 0), 'color' => 'success'],
                ['label' => __('Expenses MTD'), 'value' => '৳'.number_format($expenses, 0), 'color' => 'warning'],
                ['label' => __('Commissions MTD'), 'value' => '৳'.number_format($commissions, 0), 'color' => 'info'],
                ['label' => __('Outstanding due'), 'value' => '৳'.number_format($dues, 0), 'color' => 'danger'],
            ],
            'actions' => [
                ['label' => __('Accounts hub'), 'url' => route('accounts-hub'), 'class' => 'btn-primary'],
                ['label' => __('Expenses'), 'url' => route('admin.expenses'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function paymentReport(array $module, bool $bkashOnly): array
    {
        $from = now()->startOfMonth();
        $q = CollectionSummary::query()->where('collection_date', '>=', $from);
        if ($bkashOnly) {
            $q->where('payment_method', 'like', '%bkash%');
        }

        $rows = (clone $q)->selectRaw("COALESCE(NULLIF(payment_method,''),'unknown') as method, SUM(collection_amount) as total, COUNT(*) as cnt")
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => (string) $r->method,
                'total' => number_format((float) $r->total, 2),
                'count' => (int) $r->cnt,
            ])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Methods'), 'value' => (string) count($rows), 'color' => 'primary'],
                ['label' => __('Total'), 'value' => '৳'.number_format((float) $q->sum('collection_amount'), 0), 'color' => 'success'],
            ],
            'columns' => [
                ['key' => 'method', 'label' => __('Method')],
                ['key' => 'total', 'label' => __('Amount')],
                ['key' => 'count', 'label' => __('Count')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Full report'), 'url' => route('collection-report.index'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function paymentCenter(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Gateways'), 'value' => '3', 'color' => 'success'],
                ['label' => __('Collections today'), 'value' => '৳'.number_format((float) CollectionSummary::whereDate('collection_date', today())->sum('collection_amount'), 0), 'color' => 'primary'],
            ],
            'actions' => [
                ['label' => __('Collect payment'), 'url' => route('payment-collection'), 'class' => 'btn-success'],
                ['label' => __('Site settings'), 'url' => route('site-settings'), 'class' => 'btn-outline-secondary'],
            ],
            'notice' => __('Configure bKash, Nagad, SSLCommerz in Site Settings → Payment gateways.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function lateFees(array $module): array
    {
        $overdue = BillingInfo::query()
            ->join('customers_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->where('billing_infos.due_amount', '>', 0)
            ->whereNotIn('customers_infos.status', ['inactive', 'deleted'])
            ->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Overdue accounts'), 'value' => (string) $overdue, 'color' => 'danger'],
                ['label' => __('Total due'), 'value' => '৳'.number_format((float) BillingInfo::sum('due_amount'), 0), 'color' => 'warning'],
            ],
            'actions' => [
                ['label' => __('Billing notices'), 'url' => route('billing-notices'), 'class' => 'btn-primary'],
                ['label' => __('Automatic processes'), 'url' => route('automatic-processes'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function promotions(array $module, string $slug): array
    {
        $vouchers = Voucher::query()->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Active vouchers'), 'value' => (string) Voucher::where('status', 'active')->count(), 'color' => 'success'],
                ['label' => __('Total vouchers'), 'value' => (string) $vouchers, 'color' => 'primary'],
            ],
            'actions' => [
                ['label' => __('Voucher list'), 'url' => route('admin.vouchers'), 'class' => 'btn-primary'],
                ['label' => __('Packages'), 'url' => route('package-list-setup'), 'class' => 'btn-outline-secondary'],
            ],
            'notice' => $slug === 'coupons' ? __('Manage hotspot/reseller vouchers from Voucher list.') : __('Package promotions can be configured in Package Setup.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function smsReport(array $module): array
    {
        $logs = NotificationLogs::query()->where('type', 'like', '%sms%')->orWhere('type', 'like', '%SMS%');
        $total = (clone $logs)->count();
        $failed = (clone $logs)->where('status', 'like', '%fail%')->count();
        $rows = NotificationLogs::query()
            ->where(fn ($q) => $q->where('type', 'like', '%sms%')->orWhere('type', 'like', '%SMS%'))
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($l) => [
                'when' => $l->created_at?->format('d M H:i') ?? '—',
                'title' => Str::limit((string) $l->title, 40),
                'status' => (string) $l->status,
                'type' => (string) $l->type,
            ])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('SMS logs'), 'value' => (string) $total, 'color' => 'primary'],
                ['label' => __('Failed'), 'value' => (string) $failed, 'color' => 'danger'],
                ['label' => __('Templates'), 'value' => (string) SmsTemplate::count(), 'color' => 'success'],
            ],
            'columns' => [
                ['key' => 'when', 'label' => __('When')],
                ['key' => 'title', 'label' => __('Message')],
                ['key' => 'status', 'label' => __('Status')],
                ['key' => 'type', 'label' => __('Type')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('SMS Setup'), 'url' => route('sms-setup'), 'class' => 'btn-primary'],
                ['label' => __('All notifications'), 'url' => route('notifications'), 'class' => 'btn-outline-secondary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function commsHub(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Templates'), 'value' => (string) SmsTemplate::count(), 'color' => 'primary'],
                ['label' => __('Enabled'), 'value' => (string) SmsTemplate::where('is_active', true)->count(), 'color' => 'success'],
                ['label' => __('Notifications'), 'value' => (string) NotificationLogs::count(), 'color' => 'info'],
            ],
            'actions' => [
                ['label' => __('SMS Setup'), 'url' => route('sms-setup'), 'class' => 'btn-primary'],
                ['label' => __('SMS Notices'), 'url' => route('sms-notices'), 'class' => 'btn-outline-success'],
                ['label' => __('Outage broadcast'), 'url' => route('noc-outage'), 'class' => 'btn-outline-warning'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function whatsappBot(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Status'), 'value' => __('Configure'), 'color' => 'success'],
            ],
            'actions' => [
                ['label' => __('Site settings'), 'url' => route('site-settings'), 'class' => 'btn-primary'],
                ['label' => __('SMS templates'), 'url' => route('sms-setup'), 'class' => 'btn-outline-secondary'],
            ],
            'notice' => __('WhatsApp bot (MENU / BILL / SUPPORT) — configure API keys in Site Settings when enabled.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function nocWall(array $module): array
    {
        $online = PPPSecrets::where('is_online', true)->count();
        $routers = RouterList::count();
        $tickets = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('PPP online'), 'value' => (string) $online, 'color' => 'success'],
                ['label' => __('Routers'), 'value' => (string) $routers, 'color' => 'primary'],
                ['label' => __('Open tickets'), 'value' => (string) $tickets, 'color' => 'danger'],
            ],
            'actions' => [
                ['label' => __('NOC Overview'), 'url' => route('noc-overview'), 'class' => 'btn-primary'],
                ['label' => __('Bandwidth'), 'url' => route('bandwidth-hub'), 'class' => 'btn-outline-info'],
                ['label' => __('Online clients'), 'url' => route('online-clients'), 'class' => 'btn-outline-success'],
            ],
            'fullscreen' => true,
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function faultManagement(array $module): array
    {
        $rows = SupportTicket::query()
            ->whereIn('priority', ['high', 'urgent', 'critical'])
            ->orWhere('status', 'open')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id' => '#'.$t->id,
                'subject' => Str::limit((string) $t->subject, 50),
                'status' => (string) $t->status,
                'priority' => (string) ($t->priority ?? 'normal'),
            ])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Open faults'), 'value' => (string) SupportTicket::where('status', 'open')->count(), 'color' => 'danger'],
                ['label' => __('OLT alerts'), 'value' => (string) OltHealthLog::where('created_at', '>=', now()->subDay())->count(), 'color' => 'warning'],
            ],
            'columns' => [
                ['key' => 'id', 'label' => __('ID')],
                ['key' => 'subject', 'label' => __('Subject')],
                ['key' => 'status', 'label' => __('Status')],
                ['key' => 'priority', 'label' => __('Priority')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Support tickets'), 'url' => route('admin-tickets'), 'class' => 'btn-primary'],
                ['label' => __('Ops insights'), 'url' => route('ops-insights'), 'class' => 'btn-outline-secondary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function snmpMonitor(array $module): array
    {
        $olts = Olt::withCount('ports')->get();
        $rows = $olts->map(fn ($o) => [
            'name' => (string) $o->name,
            'host' => (string) ($o->management_ip ?? $o->snmp_host ?? '—'),
            'ports' => (string) $o->ports_count,
            'status' => (string) ($o->status ?? 'unknown'),
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('OLTs'), 'value' => (string) $olts->count(), 'color' => 'primary'],
                ['label' => __('Health logs 24h'), 'value' => (string) OltHealthLog::where('created_at', '>=', now()->subDay())->count(), 'color' => 'info'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => __('OLT')],
                ['key' => 'host', 'label' => __('Host')],
                ['key' => 'ports', 'label' => __('PON ports')],
                ['key' => 'status', 'label' => __('Status')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('OLT management'), 'url' => route('olt-management'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function netflowAnalysis(array $module): array
    {
        $rows = MikrotikLog::query()
            ->selectRaw('topic, COUNT(*) as cnt')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('topic')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get()
            ->map(fn ($r) => ['topic' => (string) $r->topic, 'count' => (int) $r->cnt])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Log events 24h'), 'value' => (string) MikrotikLog::where('created_at', '>=', now()->subDay())->count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'topic', 'label' => __('Topic')],
                ['key' => 'count', 'label' => __('Count')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Router logs'), 'url' => route('mikrotik-log-viewer'), 'class' => 'btn-primary'],
                ['label' => __('Bandwidth hub'), 'url' => route('bandwidth-hub'), 'class' => 'btn-outline-info'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function networkTopology(array $module): array
    {
        $olts = Olt::with('ports')->get();
        $rows = [];
        foreach ($olts as $olt) {
            foreach ($olt->ports as $port) {
                $rows[] = [
                    'olt' => $olt->name,
                    'pon' => $port->label ?? ('PON '.$port->pon_index),
                    'onus' => '—',
                ];
            }
        }

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('OLTs'), 'value' => (string) $olts->count(), 'color' => 'violet'],
                ['label' => __('PON ports'), 'value' => (string) count($rows), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'olt', 'label' => __('OLT')],
                ['key' => 'pon', 'label' => __('PON')],
                ['key' => 'onus', 'label' => __('ONUs')],
            ],
            'rows' => array_slice($rows, 0, 30),
            'actions' => [
                ['label' => __('OLT'), 'url' => route('olt-management'), 'class' => 'btn-primary'],
                ['label' => __('ONU'), 'url' => route('onu-management'), 'class' => 'btn-outline-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function fiberPlantMap(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Areas'), 'value' => (string) DB::table('address_fields')->count(), 'color' => 'primary'],
                ['label' => __('Customers'), 'value' => (string) CustomersInfo::count(), 'color' => 'success'],
            ],
            'actions' => [
                ['label' => __('Address setup'), 'url' => route('address-setup'), 'class' => 'btn-primary'],
                ['label' => __('ONU map data'), 'url' => route('onu-management'), 'class' => 'btn-outline-secondary'],
            ],
            'notice' => __('Fiber plant map — link customers to areas/zones. Full GIS map uses address hierarchy + ONU locations.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function popBoxes(array $module): array
    {
        $rows = RouterList::query()->get()->map(fn ($r) => [
            'name' => (string) $r->router_name,
            'ip' => (string) $r->router_ip,
            'status' => (string) ($r->is_active ?? 'active'),
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('POP / routers'), 'value' => (string) count($rows), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => __('Site')],
                ['key' => 'ip', 'label' => __('IP')],
                ['key' => 'status', 'label' => __('Status')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('MikroTik sync'), 'url' => route('mikrotik-sync'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function sessionIntegrity(array $module): array
    {
        $multi = PPPSecrets::query()
            ->select('username')
            ->groupBy('username')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Duplicate PPP names'), 'value' => (string) $multi, 'color' => 'danger'],
                ['label' => __('Online sessions'), 'value' => (string) PPPSecrets::where('is_online', true)->count(), 'color' => 'success'],
            ],
            'actions' => [
                ['label' => __('Online clients'), 'url' => route('online-clients'), 'class' => 'btn-primary'],
                ['label' => __('Automatic processes'), 'url' => route('automatic-processes'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function callReports(array $module): array
    {
        $rows = CallDeskLog::query()
            ->selectRaw('COALESCE(outcome,"unknown") as outcome, COUNT(*) as cnt')
            ->where('created_at', '>=', now()->subMonth())
            ->groupBy('outcome')
            ->get()
            ->map(fn ($r) => ['outcome' => (string) $r->outcome, 'count' => (int) $r->cnt])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Calls 30d'), 'value' => (string) CallDeskLog::where('created_at', '>=', now()->subMonth())->count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'outcome', 'label' => __('Outcome')],
                ['key' => 'count', 'label' => __('Count')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Call desk'), 'url' => route('call-desk'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function newConnections(array $module): array
    {
        $rows = PackagePurchaseRequest::query()->latest()->limit(20)->get()->map(fn ($r) => [
            'customer' => (string) ($r->customer_name ?? $r->id),
            'package' => (string) ($r->package_name ?? '—'),
            'status' => (string) ($r->status ?? 'pending'),
            'date' => $r->created_at?->format('d M Y') ?? '—',
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Pending requests'), 'value' => (string) PackagePurchaseRequest::where('status', 'pending')->count(), 'color' => 'warning'],
            ],
            'columns' => [
                ['key' => 'customer', 'label' => __('Customer')],
                ['key' => 'package', 'label' => __('Package')],
                ['key' => 'status', 'label' => __('Status')],
                ['key' => 'date', 'label' => __('Date')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Purchase requests'), 'url' => route('admin.purchase-requests'), 'class' => 'btn-primary'],
                ['label' => __('New customer'), 'url' => route('new-customer'), 'class' => 'btn-outline-success'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function salesPipeline(array $module): array
    {
        $stages = ['pending', 'approved', 'rejected', 'completed'];
        $rows = [];
        foreach ($stages as $stage) {
            $rows[] = [
                'stage' => ucfirst($stage),
                'count' => (string) PackagePurchaseRequest::where('status', $stage)->count(),
            ];
        }

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Pipeline total'), 'value' => (string) PackagePurchaseRequest::count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'stage', 'label' => __('Stage')],
                ['key' => 'count', 'label' => __('Count')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Purchase requests'), 'url' => route('admin.purchase-requests'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function taskBoard(array $module): array
    {
        $rows = SupportTicket::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->cnt])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Tasks'), 'value' => (string) SupportTicket::count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'status', 'label' => __('Status')],
                ['key' => 'count', 'label' => __('Count')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Tickets'), 'url' => route('admin-tickets'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function fieldTechnicians(array $module): array
    {
        $open = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Field tickets'), 'value' => (string) $open, 'color' => 'warning'],
                ['label' => __('Staff'), 'value' => (string) User::count(), 'color' => 'primary'],
            ],
            'actions' => [
                ['label' => __('Tickets'), 'url' => route('admin-tickets'), 'class' => 'btn-primary'],
                ['label' => __('HR hub'), 'url' => route('hr-hub'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function payrollRuns(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Staff'), 'value' => (string) User::count(), 'color' => 'primary'],
                ['label' => __('Attendance logs'), 'value' => (string) HrAttendanceLog::count(), 'color' => 'success'],
                ['label' => __('Leave requests'), 'value' => (string) HrLeaveRequest::count(), 'color' => 'warning'],
            ],
            'actions' => [
                ['label' => __('HR hub'), 'url' => route('hr-hub'), 'class' => 'btn-primary'],
                ['label' => __('Expenses'), 'url' => route('admin.expenses'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function warehouses(array $module): array
    {
        $rows = InventoryWarehouse::query()->get()->map(fn ($w) => [
            'name' => (string) $w->name,
            'code' => (string) ($w->code ?? '—'),
            'active' => $w->is_active ? __('Yes') : __('No'),
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Warehouses'), 'value' => (string) count($rows), 'color' => 'primary'],
                ['label' => __('Products'), 'value' => (string) InventoryProduct::count(), 'color' => 'success'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => __('Name')],
                ['key' => 'code', 'label' => __('Code')],
                ['key' => 'active', 'label' => __('Active')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Inventory hub'), 'url' => route('inventory-hub'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function posSales(array $module, string $slug): array
    {
        $q = InventorySale::query()->with('items');
        $rows = $q->latest()->limit(20)->get()->map(fn ($s) => [
            'id' => '#'.$s->id,
            'date' => $s->sale_date?->format('d M Y') ?? $s->created_at?->format('d M Y'),
            'total' => number_format((float) $s->total_amount, 2),
            'status' => (string) ($s->status ?? 'completed'),
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Sales'), 'value' => (string) InventorySale::count(), 'color' => 'primary'],
                ['label' => __('Revenue'), 'value' => '৳'.number_format((float) InventorySale::sum('total_amount'), 0), 'color' => 'success'],
            ],
            'columns' => [
                ['key' => 'id', 'label' => __('ID')],
                ['key' => 'date', 'label' => __('Date')],
                ['key' => 'total', 'label' => __('Total')],
                ['key' => 'status', 'label' => __('Status')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Inventory hub'), 'url' => route('inventory-hub'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function stockLedger(array $module): array
    {
        $rows = InventoryStockMovement::query()->with('product')->latest()->limit(25)->get()->map(fn ($m) => [
            'product' => (string) ($m->product?->name ?? $m->inventory_product_id),
            'type' => (string) $m->type,
            'qty' => (string) $m->quantity,
            'date' => $m->created_at?->format('d M H:i') ?? '—',
        ])->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Movements'), 'value' => (string) InventoryStockMovement::count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'product', 'label' => __('Product')],
                ['key' => 'type', 'label' => __('Type')],
                ['key' => 'qty', 'label' => __('Qty')],
                ['key' => 'date', 'label' => __('When')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Inventory'), 'url' => route('inventory-hub'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function vendors(array $module): array
    {
        $rows = InventoryPurchaseOrder::query()
            ->selectRaw('COALESCE(vendor_name,"Unknown") as vendor, COUNT(*) as cnt, SUM(total_amount) as total')
            ->groupBy('vendor_name')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'vendor' => (string) $r->vendor,
                'orders' => (int) $r->cnt,
                'total' => number_format((float) $r->total, 2),
            ])
            ->all();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('POs'), 'value' => (string) InventoryPurchaseOrder::count(), 'color' => 'primary'],
            ],
            'columns' => [
                ['key' => 'vendor', 'label' => __('Vendor')],
                ['key' => 'orders', 'label' => __('Orders')],
                ['key' => 'total', 'label' => __('Total')],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => __('Purchase requests'), 'url' => route('admin.purchase-requests'), 'class' => 'btn-primary'],
            ],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function reportsCenter(array $module): array
    {
        $modules = FeatureModuleRegistry::forGroup('Reports');

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Report modules'), 'value' => (string) count($modules), 'color' => 'primary'],
            ],
            'actions' => array_map(fn (array $m) => [
                'label' => $m['label'],
                'url' => FeatureModuleRegistry::url($m),
                'class' => 'btn-outline-primary',
            ], $modules),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function apiConfiguration(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('API'), 'value' => __('Ready'), 'color' => 'success'],
            ],
            'actions' => [
                ['label' => __('Site settings'), 'url' => route('site-settings'), 'class' => 'btn-primary'],
                ['label' => __('SMS Bridge'), 'url' => route('sms-bridge.index'), 'class' => 'btn-outline-secondary'],
            ],
            'notice' => __('REST API tokens and HMAC — configure in Site Settings.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function performanceSettings(array $module): array
    {
        $processes = AutomaticProcess::where('enabled', true)->count();

        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Auto processes on'), 'value' => (string) $processes, 'color' => 'success'],
                ['label' => __('Routers'), 'value' => (string) RouterList::count(), 'color' => 'primary'],
            ],
            'actions' => [
                ['label' => __('Automatic processes'), 'url' => route('automatic-processes'), 'class' => 'btn-primary'],
                ['label' => __('Site settings'), 'url' => route('site-settings'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function queueMonitor(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Queue driver'), 'value' => config('queue.default', 'sync'), 'color' => 'primary'],
                ['label' => __('Failed jobs table'), 'value' => Schema::hasTable('failed_jobs') ? __('Yes') : __('No'), 'color' => 'info'],
            ],
            'actions' => [
                ['label' => __('System logs'), 'url' => route('admin.system-logs'), 'class' => 'btn-primary'],
                ['label' => __('Automatic processes'), 'url' => route('automatic-processes'), 'class' => 'btn-outline-secondary'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function mobileApps(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Technician API'), 'value' => __('Available'), 'color' => 'success'],
            ],
            'actions' => [
                ['label' => __('Site settings'), 'url' => route('site-settings'), 'class' => 'btn-primary'],
            ],
            'notice' => __('Mobile technician app connects via API — configure base URL in Site Settings.'),
            'columns' => [],
            'rows' => [],
        ]);
    }

    /** @param array<string, mixed> $module */
    private function aiCopilot(array $module): array
    {
        return array_merge($this->base($module), [
            'kpis' => [
                ['label' => __('Insights'), 'value' => (string) NotificationLogs::count(), 'color' => 'indigo'],
            ],
            'actions' => [
                ['label' => __('Ops insights'), 'url' => route('ops-insights'), 'class' => 'btn-primary'],
                ['label' => __('NOC overview'), 'url' => route('noc-overview'), 'class' => 'btn-outline-info'],
                ['label' => __('Billing notices'), 'url' => route('billing-notices'), 'class' => 'btn-outline-success'],
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }
}
