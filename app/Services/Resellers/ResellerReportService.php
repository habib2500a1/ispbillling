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
     * @return array{total_commission: float, pending: float, paid: float, partners: int, rows: list<array<string, mixed>>}
     */
    public function summary(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $query = ResellerCommission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('earned_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('earned_at')->whereBetween('created_at', [$from, $to]);
                    });
            });

        if ($resellerId !== null) {
            $query->where('reseller_id', $resellerId);
        }

        $total = (float) (clone $query)->sum('commission_amount');
        $pending = (float) (clone $query)->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount');
        $paid = (float) (clone $query)->where('status', ResellerCommission::STATUS_PAID)->sum('commission_amount');

        $byReseller = ResellerCommission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('earned_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('earned_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->when($resellerId !== null, fn ($q) => $q->where('reseller_id', $resellerId))
            ->select(
                'reseller_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('SUM(commission_amount) as total'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_total"),
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
        ])->all();

        return [
            'total_commission' => round($total, 2),
            'pending' => round($pending, 2),
            'paid' => round($paid, 2),
            'partners' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detailRows(Carbon $from, Carbon $to, ?int $resellerId = null, ?int $tenantId = null, int $limit = 500): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return ResellerCommission::withoutGlobalScopes()
            ->with(['reseller', 'customer'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to): void {
                $q->whereBetween('earned_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to): void {
                        $q2->whereNull('earned_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->when($resellerId !== null, fn ($q) => $q->where('reseller_id', $resellerId))
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
