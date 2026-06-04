<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResellerReportService
{
    /**
     * @return \Illuminate\Database\Query\Expression|string
     */
    private function earnedMonthSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(COALESCE(earned_at, created_at), 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', COALESCE(earned_at, created_at))",
            default => "DATE_FORMAT(COALESCE(earned_at, created_at), '%Y-%m')",
        };
    }

    private function basePeriodQuery(Carbon $from, Carbon $to, ?int $resellerId, int $tenantId)
    {
        return ResellerCommission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('earned_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('earned_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->when($resellerId !== null, fn ($q) => $q->where('reseller_id', $resellerId));
    }

    /**
     * @return array{total_commission: float, pending: float, paid: float, cancelled: float, partners: int, rows: list<array<string, mixed>>}
     */
    public function summary(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null, ?string $statusFilter = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $query = $this->basePeriodQuery($from, $to, $resellerId, $tenantId);
        if ($statusFilter !== null && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $total = (float) (clone $query)->sum('commission_amount');
        $pending = (float) (clone $this->basePeriodQuery($from, $to, $resellerId, $tenantId))
            ->where('status', ResellerCommission::STATUS_PENDING)
            ->sum('commission_amount');
        $paid = (float) (clone $this->basePeriodQuery($from, $to, $resellerId, $tenantId))
            ->where('status', ResellerCommission::STATUS_PAID)
            ->sum('commission_amount');
        $cancelled = (float) (clone $this->basePeriodQuery($from, $to, $resellerId, $tenantId))
            ->where('status', ResellerCommission::STATUS_CANCELLED)
            ->sum('commission_amount');

        $byResellerQuery = $this->basePeriodQuery($from, $to, $resellerId, $tenantId);
        if ($statusFilter !== null && $statusFilter !== 'all') {
            $byResellerQuery->where('status', $statusFilter);
        }

        $byReseller = $byResellerQuery
            ->select(
                'reseller_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(commission_amount) as total'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_total"),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_total"),
            )
            ->groupBy('reseller_id')
            ->orderByDesc('total')
            ->get();

        $resellerNames = Reseller::withoutGlobalScopes()
            ->whereIn('id', $byReseller->pluck('reseller_id'))
            ->pluck('name', 'id');

        $rows = $byReseller->map(fn ($row): array => [
            'reseller_id' => (int) $row->reseller_id,
            'reseller' => $resellerNames[$row->reseller_id] ?? '—',
            'transactions' => (int) $row->cnt,
            'commission' => round((float) $row->total, 2),
            'pending' => round((float) $row->pending_total, 2),
            'paid' => round((float) $row->paid_total, 2),
        ])->all();

        return [
            'total_commission' => round($total, 2),
            'pending' => round($pending, 2),
            'paid' => round($paid, 2),
            'cancelled' => round($cancelled, 2),
            'partners' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Commission totals grouped by calendar month (newest first).
     *
     * @return list<array{month: string, label: string, count: int, total: float, pending: float, paid: float, cancelled: float}>
     */
    public function monthlyBreakdown(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null, ?string $statusFilter = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $monthSql = $this->earnedMonthSql();

        $query = $this->basePeriodQuery($from, $to, $resellerId, $tenantId);
        if ($statusFilter !== null && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $rows = $query
            ->select(
                DB::raw("{$monthSql} as month_key"),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(commission_amount) as total'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_total"),
                DB::raw("SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_total"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN commission_amount ELSE 0 END) as cancelled_total"),
            )
            ->groupByRaw($monthSql)
            ->orderByDesc(DB::raw($monthSql))
            ->get();

        return $rows->map(function ($row): array {
            $key = (string) $row->month_key;
            $label = $key !== ''
                ? Carbon::createFromFormat('Y-m', $key)->format('F Y')
                : 'Unknown';

            return [
                'month' => $key,
                'label' => $label,
                'count' => (int) $row->cnt,
                'total' => round((float) $row->total, 2),
                'pending' => round((float) $row->pending_total, 2),
                'paid' => round((float) $row->paid_total, 2),
                'cancelled' => round((float) $row->cancelled_total, 2),
            ];
        })->all();
    }

    /**
     * @return list<array{status: string, label: string, count: int, amount: float}>
     */
    public function statusBreakdown(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $rows = $this->basePeriodQuery($from, $to, $resellerId, $tenantId)
            ->select(
                'status',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(commission_amount) as total'),
            )
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $labels = [
            ResellerCommission::STATUS_PENDING => 'Pending',
            ResellerCommission::STATUS_PAID => 'Paid',
            ResellerCommission::STATUS_CANCELLED => 'Cancelled',
        ];

        return $rows->map(fn ($row): array => [
            'status' => (string) $row->status,
            'label' => $labels[$row->status] ?? ucfirst((string) $row->status),
            'count' => (int) $row->cnt,
            'amount' => round((float) $row->total, 2),
        ])->all();
    }

    /**
     * @return array{pending: float, paid: float, cancelled: float, count_pending: int, count_paid: int}
     */
    public function partnerLedgerTotals(int $resellerId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subMonths(11)->startOfMonth();
        $to ??= now()->endOfDay();

        $query = $this->basePeriodQuery($from, $to, $resellerId, TenantResolver::requiredTenantId());

        return [
            'pending' => round((float) (clone $query)->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount'), 2),
            'paid' => round((float) (clone $query)->where('status', ResellerCommission::STATUS_PAID)->sum('commission_amount'), 2),
            'cancelled' => round((float) (clone $query)->where('status', ResellerCommission::STATUS_CANCELLED)->sum('commission_amount'), 2),
            'count_pending' => (int) (clone $query)->where('status', ResellerCommission::STATUS_PENDING)->count(),
            'count_paid' => (int) (clone $query)->where('status', ResellerCommission::STATUS_PAID)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detailRows(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null, int $limit = 500, ?string $statusFilter = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $query = $this->basePeriodQuery($from, $to, $resellerId, $tenantId);
        if ($statusFilter !== null && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        return $query
            ->with(['reseller', 'customer'])
            ->orderByDesc('earned_at')
            ->limit($limit)
            ->get()
            ->map(fn (ResellerCommission $c): array => [
                'earned_at' => $c->earned_at?->format('Y-m-d H:i') ?? '',
                'reseller' => $c->reseller?->name ?? '—',
                'customer' => $c->customer?->name ?? '—',
                'gross' => (float) $c->gross_amount,
                'commission' => (float) $c->commission_amount,
                'status' => $c->status,
            ])
            ->all();
    }
}
