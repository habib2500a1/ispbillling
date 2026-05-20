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
use App\Support\CustomerStatus;
use App\Support\InternalTaskStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class StaffMobileService
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly StaffBillingKpiResolver $billingKpis,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $tenantId = (int) $user->tenant_id;
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $billing = $this->billingKpis->resolve($tenantId);
        $snap = $this->metrics->snapshot($tenantId);
        $expiringToday = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('service_expires_at', today())
            ->count();

        return [
            'kpis' => [
                'collected_today' => round((float) ($snap['collected_today'] ?? 0), 2),
                'active_clients' => (int) ($snap['active_subscribers'] ?? 0),
                'due_clients' => $this->billingKpis->dueClientsCount($tenantId),
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
            'billing' => $billing,
            'tickets' => $this->ticketStats($tenantId),
            'tasks' => $this->taskStats($tenantId),
            'zone_collection_chart' => $this->zoneCollectionChartFromSynced($tenantId, $from, $to),
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
    private function zoneCollectionChartFromSynced(int $tenantId, Carbon $from, Carbon $to): array
    {
        $zones = Zone::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name']);

        $rows = [];
        $periodKey = now()->format('Y-m');

        foreach ($zones as $zone) {
            $customerIds = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('zone_id', $zone->id)
                ->pluck('id');

            if ($customerIds->isEmpty()) {
                continue;
            }

            $paid = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('customer_id', $customerIds)
                ->where('invoice_number', 'like', 'ISD-%-'.$periodKey)
                ->sum('amount_paid');

            $unpaid = (float) Customer::withoutGlobalScopes()
                ->whereIn('id', $customerIds)
                ->get()
                ->sum(fn (Customer $c): float => (float) ($c->meta['isp_digital_balance_due'] ?? 0));

            if ($paid <= 0 && $unpaid <= 0) {
                $paid = (float) Payment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereBetween('paid_at', [$from, $to])
                    ->whereIn('customer_id', $customerIds)
                    ->sum('amount');
                $unpaid = (float) Invoice::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('customer_id', $customerIds)
                    ->whereNotIn('status', ['paid', 'void', 'cancelled'])
                    ->sum(DB::raw('GREATEST(total - amount_paid, 0)'));
            }

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
