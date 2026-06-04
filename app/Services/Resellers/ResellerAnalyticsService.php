<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Network-wide reseller analytics for the admin Resellers hub.
 *
 * Every figure is computed with grouped/aggregate queries (no per-reseller query loop), so the
 * dashboard stays fast as the partner count grows. Read-only; reuses existing columns only.
 */
final class ResellerAnalyticsService
{
    /**
     * Commission + revenue trend for the last N months (oldest → newest).
     *
     * @return array<int, array{label: string, commission: float, gross: float, paid: float}>
     */
    public function commissionTrend(int $months = 6, ?int $tenantId = null): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);
        $ymExpr = $this->monthExpr('earned_at');

        $gross = ResellerCommission::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('earned_at', '>=', $start)
            ->selectRaw("$ymExpr as ym")
            ->selectRaw('COALESCE(SUM(commission_amount),0) as commission')
            ->selectRaw('COALESCE(SUM(gross_amount),0) as gross')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END),0) as paid")
            ->groupByRaw($ymExpr)
            ->get()
            ->keyBy('ym');

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $ym = $m->format('Y-m');
            $row = $gross[$ym] ?? null;
            $out[] = [
                'label' => $m->format('M'),
                'commission' => round((float) ($row->commission ?? 0), 2),
                'gross' => round((float) ($row->gross ?? 0), 2),
                'paid' => round((float) ($row->paid ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * New reseller-linked customers per month for the last N months (growth).
     *
     * @return array<int, array{label: string, customers: int}>
     */
    public function customerGrowth(int $months = 6, ?int $tenantId = null): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);
        $ymExpr = $this->monthExpr('created_at');

        $rows = Customer::query()
            ->whereNotNull('reseller_id')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $start)
            ->selectRaw("$ymExpr as ym")
            ->selectRaw('COUNT(*) as c')
            ->groupByRaw($ymExpr)
            ->pluck('c', 'ym');

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $out[] = [
                'label' => $m->format('M'),
                'customers' => (int) ($rows[$m->format('Y-m')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Network financial overview — single aggregate pass.
     *
     * @return array<string, float|int>
     */
    public function financialOverview(?int $tenantId = null): array
    {
        $resellerQ = Reseller::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $wallet = (clone $resellerQ)->sum('wallet_balance');
        $bonus = (clone $resellerQ)->sum('bonus_wallet_balance');
        $receivable = (clone $resellerQ)->sum('admin_receivable_due');
        $creditLimit = (clone $resellerQ)->sum('credit_limit');
        $margin = (clone $resellerQ)->sum('margin_accrued_total');
        $negativeWallets = (clone $resellerQ)->where('wallet_balance', '<', 0)->count();
        $frozen = (clone $resellerQ)->where('wallet_frozen', true)->count();

        $commissionQ = ResellerCommission::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
        $byStatus = (clone $commissionQ)
            ->selectRaw('status, COALESCE(SUM(commission_amount),0) as amt, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $amt = fn (string $s): float => (float) ($byStatus[$s]->amt ?? 0);
        $cnt = fn (string $s): int => (int) ($byStatus[$s]->cnt ?? 0);

        $pending = $amt(ResellerCommission::STATUS_PENDING);
        $paid = $amt(ResellerCommission::STATUS_PAID);

        return [
            'wallet_total' => round((float) $wallet, 2),
            'bonus_total' => round((float) $bonus, 2),
            'admin_receivable' => round((float) $receivable, 2),
            'credit_limit_total' => round((float) $creditLimit, 2),
            'credit_util' => $creditLimit > 0 ? round(($receivable / $creditLimit) * 100, 1) : 0.0,
            'margin_accrued' => round((float) $margin, 2),
            'negative_wallets' => $negativeWallets,
            'frozen_wallets' => $frozen,
            'commission_pending' => round($pending, 2),
            'commission_pending_count' => $cnt(ResellerCommission::STATUS_PENDING),
            'commission_paid' => round($paid, 2),
            'commission_paid_count' => $cnt(ResellerCommission::STATUS_PAID),
            'commission_lifetime' => round($pending + $paid, 2),
        ];
    }

    /**
     * Risk monitoring — partners flagged by credit utilisation, negative wallet, or risk score.
     * One grouped pass for customer counts, then in-memory scoring (no N+1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function riskWatchlist(int $limit = 8, ?int $tenantId = null): array
    {
        $resellers = Reseller::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'name', 'code', 'is_active', 'wallet_balance', 'credit_limit',
                'admin_receivable_due', 'risk_score', 'wallet_frozen', 'low_balance_threshold']);

        $customerCounts = $this->customerCountsByReseller($tenantId);

        return $resellers->map(function (Reseller $r) use ($customerCounts): array {
            $limit = (float) $r->credit_limit;
            $due = (float) $r->admin_receivable_due;
            $wallet = (float) $r->wallet_balance;
            $util = $limit > 0 ? round(($due / $limit) * 100, 1) : 0.0;

            $flags = [];
            if ($wallet < 0) {
                $flags[] = 'Negative wallet';
            } elseif ((float) $r->low_balance_threshold > 0 && $wallet < (float) $r->low_balance_threshold) {
                $flags[] = 'Low balance';
            }
            if ($util >= 90) {
                $flags[] = 'Credit '.$util.'%';
            }
            if ($r->wallet_frozen) {
                $flags[] = 'Frozen';
            }
            if ((float) $r->risk_score >= 70) {
                $flags[] = 'Risk '.(int) $r->risk_score;
            }

            // Severity for ranking: blend credit util, risk score, negative wallet.
            $severity = $util + (float) $r->risk_score + ($wallet < 0 ? 100 : 0);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'active' => (bool) $r->is_active,
                'customers' => $customerCounts[$r->id] ?? 0,
                'wallet' => round($wallet, 2),
                'credit_util' => $util,
                'risk_score' => (float) $r->risk_score,
                'flags' => $flags,
                'severity' => $severity,
            ];
        })
            ->filter(fn (array $row): bool => $row['flags'] !== [])
            ->sortByDesc('severity')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Partner performance scores (0–100) blending base size, collection health and risk.
     * Reuses ResellerCollectionPerformanceService output (already computed) for due/collection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function performanceScores(int $limit = 8, ?int $tenantId = null): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = app(ResellerCollectionPerformanceService::class)->partnerRows(null, null, $tenantId);

        $maxCustomers = max(1, (int) collect($rows)->max('customers'));

        $scored = collect($rows)->map(function (array $r) use ($maxCustomers): array {
            // Size 0-30, collection 0-45, low-risk 0-25.
            $sizeScore = min(30, ($r['customers'] / $maxCustomers) * 30);
            $collScore = ($r['collection_rate'] / 100) * 45;
            $riskScore = (1 - min(100, (float) $r['risk_score']) / 100) * 25;
            $score = (int) round($sizeScore + $collScore + $riskScore);

            return [
                'id' => $r['id'],
                'name' => $r['name'],
                'code' => $r['code'],
                'active' => $r['active'],
                'customers' => $r['customers'],
                'collection_rate' => $r['collection_rate'],
                'customer_due' => $r['customer_due'],
                'collected' => $r['collected'],
                'credit_util' => $r['credit_util'],
                'risk_score' => $r['risk_score'],
                'score' => max(0, min(100, $score)),
                'grade' => $this->grade($score),
            ];
        });

        return $scored->sortByDesc('score')->take($limit)->values()->all();
    }

    /**
     * Customer counts grouped by reseller — single query.
     *
     * @return array<int, int>
     */
    private function customerCountsByReseller(?int $tenantId = null): array
    {
        return Customer::query()
            ->whereNotNull('reseller_id')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('reseller_id, COUNT(*) as c')
            ->groupBy('reseller_id')
            ->pluck('c', 'reseller_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default => 'E',
        };
    }

    /**
     * Driver-portable "YYYY-MM" month bucket expression for GROUP BY.
     */
    private function monthExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "to_char($column, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', $column)",
            default => "DATE_FORMAT($column, '%Y-%m')",
        };
    }
}
