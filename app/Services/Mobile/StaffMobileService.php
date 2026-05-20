<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\InternalTask;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Zone;
use App\Services\Dashboard\DashboardMetricsService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\CustomerStatus;
use App\Support\InternalTaskStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class StaffMobileService
{
    public function __construct(
        private readonly AnalyticsReportService $analytics,
        private readonly DashboardMetricsService $metrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $tenantId = (int) $user->tenant_id;
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $summary = $this->analytics->summary($from, $to, $tenantId);

        $monthlyBill = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['void', 'cancelled'])
            ->sum('total');

        $discount = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum(DB::raw('COALESCE(discount_amount, 0) + COALESCE(coupon_discount_amount, 0)'));

        $snap = $this->metrics->snapshot($tenantId);
        $expiringToday = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('service_expires_at', today())
            ->count();

        return [
            'kpis' => [
                'collected_today' => round((float) ($snap['collected_today'] ?? 0), 2),
                'active_clients' => (int) ($snap['active_subscribers'] ?? 0),
                'due_clients' => (int) ($snap['due_customers'] ?? 0),
                'expiring_today' => $expiringToday,
                'online_clients' => Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', CustomerStatus::ACTIVE)
                    ->limit(500)
                    ->get()
                    ->filter(fn (Customer $c) => $c->isPppOnline())
                    ->count(),
            ],
            'revenue_chart_7d' => $this->metrics->revenueTrend(7, $tenantId),
            'app_modules' => $this->appModules($user),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values()->all(),
                'user_type' => $this->userTypeLabel($user),
                'status' => $user->is_active ? 'Active' : 'Inactive',
            ],
            'billing' => [
                'monthly_bill' => round($monthlyBill, 2),
                'collected_bill' => round((float) ($summary['collected'] ?? 0), 2),
                'due' => round((float) ($summary['outstanding'] ?? 0), 2),
                'discount' => round(abs($discount), 2),
            ],
            'tickets' => $this->ticketStats($tenantId),
            'tasks' => $this->taskStats($tenantId),
            'zone_collection_chart' => $this->zoneCollectionChart($tenantId, $from, $to),
            'quick_actions' => $this->quickActions($user),
        ];
    }

    /**
     * @return array{total: int, pending: int, process: int}
     */
    private function ticketStats(int $tenantId): array
    {
        $base = SupportTicket::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $pending = (clone $base)->whereIn('status', ['open', 'waiting'])->count();
        $process = (clone $base)->where('status', 'in_progress')->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'process' => $process,
        ];
    }

    /**
     * @return array{total: int, pending: int, process: int}
     */
    private function taskStats(int $tenantId): array
    {
        $base = InternalTask::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $pending = (clone $base)->where('status', InternalTaskStatus::PENDING)->count();
        $process = (clone $base)->where('status', InternalTaskStatus::IN_PROGRESS)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'process' => $process,
        ];
    }

    /**
     * @return list<array{zone: string, paid: float, unpaid: float}>
     */
    private function zoneCollectionChart(int $tenantId, Carbon $from, Carbon $to): array
    {
        $zones = Zone::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);

        $rows = [];

        foreach ($zones as $zone) {
            $paid = (float) Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$from, $to])
                ->whereHas('customer', fn ($q) => $q->where('zone_id', $zone->id))
                ->sum('amount');

            $unpaid = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')
                ->whereHas('customer', fn ($q) => $q->where('zone_id', $zone->id))
                ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
                ->value('due');

            $rows[] = [
                'zone' => $zone->name,
                'paid' => round($paid, 2),
                'unpaid' => round($unpaid, 2),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, icon: string}>
     */
    private function quickActions(User $user): array
    {
        $actions = [
            ['key' => 'collect', 'label' => 'Bill Receive', 'icon' => 'payments'],
            ['key' => 'monitoring', 'label' => 'Monitoring', 'icon' => 'monitor'],
            ['key' => 'add_client', 'label' => 'Add Client', 'icon' => 'person_add'],
            ['key' => 'clients', 'label' => 'Client List', 'icon' => 'groups'],
        ];

        if ($user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager'])) {
            $actions[] = ['key' => 'billing', 'label' => 'Billing List', 'icon' => 'receipt'];
            $actions[] = ['key' => 'tickets', 'label' => 'Create Ticket', 'icon' => 'support'];
        }

        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager', 'super-admin'])) {
            $actions[] = ['key' => 'approval', 'label' => 'Bill Approval', 'icon' => 'verified'];
            $actions[] = ['key' => 'expense', 'label' => 'Expense', 'icon' => 'account_balance'];
        }

        return $actions;
    }

    /**
     * @return list<array{key: string, title: string, subtitle: string, icon: string, color: string}>
     */
    private function appModules(User $user): array
    {
        $modules = [
            ['key' => 'clients', 'title' => 'Clients', 'subtitle' => 'List · Add · Edit', 'icon' => 'groups', 'color' => 'orange'],
            ['key' => 'billing', 'title' => 'Billing', 'subtitle' => 'Due · Pay · Invoices', 'icon' => 'receipt', 'color' => 'red'],
            ['key' => 'packages', 'title' => 'Packages', 'subtitle' => 'Plans · Change', 'icon' => 'inventory', 'color' => 'purple'],
            ['key' => 'mikrotik', 'title' => 'MikroTik', 'subtitle' => 'Online · Suspend', 'icon' => 'router', 'color' => 'blue'],
            ['key' => 'reports', 'title' => 'Reports', 'subtitle' => 'Collection · Due', 'icon' => 'analytics', 'color' => 'teal'],
            ['key' => 'support', 'title' => 'Tickets', 'subtitle' => 'Support list', 'icon' => 'support', 'color' => 'indigo'],
            ['key' => 'comms', 'title' => 'SMS & Notice', 'subtitle' => 'Remind · Broadcast', 'icon' => 'sms', 'color' => 'pink'],
            ['key' => 'profile', 'title' => 'Profile', 'subtitle' => 'Password', 'icon' => 'person', 'color' => 'slate'],
        ];

        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager', 'super-admin', 'isp-admin'])) {
            array_splice($modules, 2, 0, [[
                'key' => 'collect',
                'title' => 'Collection',
                'subtitle' => 'Receive payment',
                'icon' => 'payments',
                'color' => 'green',
            ]]);
        }

        return $modules;
    }

    private function userTypeLabel(User $user): string
    {
        if ($user->hasRole('super-admin') || $user->hasRole('isp-admin')) {
            return 'Admin';
        }

        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager'])) {
            return 'Staff';
        }

        if ($user->hasAnyRole(['isp-engineer', 'isp-support', 'isp-manager'])) {
            return 'Staff';
        }

        return 'Staff';
    }
}
