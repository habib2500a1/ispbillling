<?php

namespace App\Services\Dashboard;

use App\Models\CallDeskLog;
use App\Models\CollectionSummary;
use App\Models\PPPSecrets;
use App\Models\HrAttendanceLog;
use App\Models\HrLeaveRequest;
use App\Models\InventoryProduct;
use App\Models\RouterList;
use App\Services\Billing\BillingNoticesService;
use App\Services\Support\TicketSlaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ops command-center KPIs for main dashboard (hubs from phases 3–10).
 */
final class DashboardOpsService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $data = [
            'today_collection' => 0.0,
            'overdue_notices' => 0,
            'due_soon' => 0,
            'open_tickets' => 0,
            'sla_breached' => 0,
            'calls_today' => 0,
            'callbacks' => 0,
            'low_stock' => 0,
            'products' => 0,
            'present_today' => 0,
            'pending_leaves' => 0,
            'routers_connected' => 0,
            'routers' => 0,
            'online_clients' => 0,
            'links' => [
                'accounts' => 'accounts-hub',
                'billing_notices' => 'billing-notices',
                'call_desk' => 'admin-tickets',
                'sms_notices' => 'sms-notices',
                'inventory' => 'inventory-hub',
                'hr' => 'hr-hub',
                'bandwidth' => 'bandwidth-hub',
                'noc' => 'admin-tickets',
            ],
        ];

        try {
            $data['today_collection'] = round((float) CollectionSummary::query()
                ->whereDate('collection_date', today())
                ->sum('collection_amount'), 2);

            $notices = app(BillingNoticesService::class)->payload(3, 50);
            $data['overdue_notices'] = (int) ($notices['summary']['overdue'] ?? 0);
            $data['due_soon'] = (int) ($notices['summary']['due_soon'] ?? 0);

            $sla = app(TicketSlaService::class)->summaryCounts();
            $data['open_tickets'] = (int) (($sla['open'] ?? 0) + ($sla['in_progress'] ?? 0));
            $data['sla_breached'] = (int) ($sla['breached'] ?? 0);

            $data['routers'] = RouterList::query()->count();
            $data['routers_connected'] = RouterList::query()->where('action', 'connected')->count();
            $data['online_clients'] = PPPSecrets::query()
                ->visibleToViewer()
                ->whereNotNull('uptime')
                ->where('status', '!=', 'removed')
                ->count();

            if (Schema::hasTable('call_desk_logs')) {
                $data['calls_today'] = CallDeskLog::query()->whereDate('called_at', today())->count();
                $data['callbacks'] = CallDeskLog::query()
                    ->where('outcome', 'callback')
                    ->where('called_at', '>=', now()->subDays(7))
                    ->count();
            }

            if (Schema::hasTable('inventory_products')) {
                $data['products'] = InventoryProduct::query()->where('is_active', true)->count();
                $data['low_stock'] = InventoryProduct::query()
                    ->where('is_active', true)
                    ->where('reorder_level', '>', 0)
                    ->whereColumn('stock_qty', '<=', 'reorder_level')
                    ->count();
            }

            if (Schema::hasTable('hr_attendance_logs')) {
                $data['present_today'] = HrAttendanceLog::query()
                    ->whereDate('work_date', today())
                    ->whereIn('status', ['present', 'late', 'half_day'])
                    ->count();
            }

            if (Schema::hasTable('hr_leave_requests')) {
                $data['pending_leaves'] = HrLeaveRequest::query()->where('status', 'pending')->count();
            }
        } catch (\Throwable $e) {
            Log::warning('dashboard ops snapshot failed', ['error' => $e->getMessage()]);
        }

        return $data;
    }
}
