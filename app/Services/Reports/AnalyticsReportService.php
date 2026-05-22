<?php

namespace App\Services\Reports;

use App\Models\Area;
use App\Models\Customer;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PppSessionLog;
use App\Models\Zone;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $collected = $this->totalCollected($from, $to, $tenantId);
        $invoiced = $this->totalInvoiced($from, $to, $tenantId);
        $due = $this->totalOutstanding($tenantId);
        $active = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', CustomerStatus::ACTIVE)->count();
        $bandwidth = app(BandwidthCollectionService::class);
        $online = $bandwidth->tenantOnlineFlagsTrustworthy($tenantId)
            ? PppSessionLog::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'active')->count()
            : 0;
        $newSubs = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('joined_at', [$from->toDateString(), $to->toDateString()])
            ->count();
        $churned = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [CustomerStatus::TERMINATED, CustomerStatus::SUSPENDED])
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        return [
            'collected' => $collected,
            'invoiced' => $invoiced,
            'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
            'outstanding' => $due,
            'active_subscribers' => $active,
            'online_now' => $online,
            'new_subscribers' => $newSubs,
            'churned' => $churned,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectionReport(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $byMethod = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->select('method', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(amount) as total'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) ($row->method ?? 'unknown'),
                'count' => (int) $row->cnt,
                'amount' => round((float) $row->total, 2),
            ])
            ->all();

        $byDay = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->select(DB::raw('DATE(paid_at) as day'), DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->day,
                'count' => (int) $row->cnt,
                'amount' => round((float) $row->total, 2),
            ])
            ->all();

        return [
            'by_method' => $byMethod,
            'by_day' => $byDay,
            'total' => $this->totalCollected($from, $to, $tenantId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueReport(?int $tenantId = null, int $limit = 200): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $today = now()->startOfDay();

        return Invoice::withoutGlobalScopes()
            ->with(['customer.area'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->whereRaw('(total - amount_paid) > 0')
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(function (Invoice $inv) use ($today): array {
                $due = round((float) $inv->total - (float) $inv->amount_paid, 2);
                $dueDate = $inv->due_date;
                $daysOverdue = ($dueDate && $dueDate->lt($today)) ? $dueDate->diffInDays($today) : 0;

                return [
                    'invoice_number' => $inv->invoice_number,
                    'customer' => $inv->customer?->name ?? '—',
                    'customer_code' => $inv->customer?->customer_code ?? '—',
                    'area' => $inv->customer?->area?->name ?? '—',
                    'due_date' => $dueDate?->toDateString(),
                    'days_overdue' => $daysOverdue,
                    'balance_due' => $due,
                    'status' => $inv->status,
                ];
            })
            ->all();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, aging: array<string, float>, count: int}
     */
    public function dueReportPro(?int $tenantId = null, int $limit = 500): array
    {
        $rows = $this->dueReport($tenantId, $limit);

        $aging = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_plus' => 0.0,
            'total' => 0.0,
        ];

        foreach ($rows as &$row) {
            $due = (float) $row['balance_due'];
            $days = (int) ($row['days_overdue'] ?? 0);
            $bucket = 'Current';
            if ($days <= 0) {
                $aging['current'] += $due;
            } elseif ($days <= 30) {
                $aging['days_1_30'] += $due;
                $bucket = '1–30 days';
            } elseif ($days <= 60) {
                $aging['days_31_60'] += $due;
                $bucket = '31–60 days';
            } else {
                $aging['days_61_plus'] += $due;
                $bucket = '61+ days';
            }
            $aging['total'] += $due;
            $row['aging_bucket'] = $bucket;
        }
        unset($row);

        foreach ($aging as $key => $value) {
            $aging[$key] = round($value, 2);
        }

        return [
            'rows' => $rows,
            'aging' => $aging,
            'count' => count($rows),
        ];
    }

    /**
     * @return array{labels: list<string>, invoiced: list<float>, collected: list<float>, totals: array<string, float>}
     */
    public function revenueAnalytics(int $months = 12, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $labels = [];
        $invoiced = [];
        $collected = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $key = $month->format('M Y');
            $labels[] = $key;

            $invoiced[] = round((float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total'), 2);

            $collected[] = round((float) Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount'), 2);
        }

        return [
            'labels' => $labels,
            'invoiced' => $invoiced,
            'collected' => $collected,
            'totals' => [
                'invoiced' => array_sum($invoiced),
                'collected' => array_sum($collected),
            ],
        ];
    }

    /**
     * @return array{churned: list<array<string, mixed>>, by_status: list<array<string, mixed>>}
     */
    public function churnAnalysis(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $byStatus = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [CustomerStatus::SUSPENDED, CustomerStatus::TERMINATED, CustomerStatus::EXPIRED])
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => CustomerStatus::label((string) $row->status),
                'count' => (int) $row->cnt,
            ])
            ->all();

        $churned = Customer::withoutGlobalScopes()
            ->with('package')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [CustomerStatus::TERMINATED, CustomerStatus::SUSPENDED, CustomerStatus::EXPIRED])
            ->whereBetween('updated_at', [$from, $to])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Customer $c): array => [
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'status' => $c->statusLabel(),
                'package' => $c->package?->name ?? '—',
                'updated_at' => $c->updated_at?->toDateString(),
            ])
            ->all();

        return ['churned' => $churned, 'by_status' => $byStatus];
    }

    /**
     * @return array{labels: list<string>, new_subscribers: list<int>, total_active: list<int>}
     */
    public function subscriberGrowth(int $months = 12, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $labels = [];
        $newSubs = [];
        $totalActive = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $end = $month->copy()->endOfMonth();
            $labels[] = $month->format('M Y');

            $newSubs[] = (int) Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('joined_at', '>=', $month->copy()->startOfMonth()->toDateString())
                ->whereDate('joined_at', '<=', $end->toDateString())
                ->count();

            $totalActive[] = (int) Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', CustomerStatus::ACTIVE)
                ->where(function ($q) use ($end): void {
                    $q->whereNull('joined_at')->orWhereDate('joined_at', '<=', $end->toDateString());
                })
                ->count();
        }

        return [
            'labels' => $labels,
            'new_subscribers' => $newSubs,
            'total_active' => $totalActive,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function onlineUserReport(?int $tenantId = null, int $limit = 300): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return PppSessionLog::withoutGlobalScopes()
            ->with(['customer.area', 'customer.package'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (PppSessionLog $s): array => [
                'customer' => $s->customer?->name ?? $s->username,
                'code' => $s->customer?->customer_code ?? '—',
                'username' => $s->username,
                'area' => $s->customer?->area?->name ?? '—',
                'package' => $s->customer?->package?->name ?? '—',
                'ip' => $s->framed_ip ?? '—',
                'download' => $s->liveDownloadBps(),
                'upload' => $s->liveUploadBps(),
                'started_at' => $s->started_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function areaWiseReport(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $areas = Area::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($areas as $area) {
            $customerIds = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('area_id', $area->id)
                ->pluck('id');

            $active = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('area_id', $area->id)
                ->where('status', CustomerStatus::ACTIVE)
                ->count();

            $collected = $customerIds->isEmpty() ? 0 : (float) Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereIn('customer_id', $customerIds)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount');

            $due = $customerIds->isEmpty() ? 0 : (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')
                ->sum(DB::raw('total - amount_paid'));

            $rows[] = [
                'area' => $area->name,
                'code' => $area->code ?? '—',
                'total_customers' => $customerIds->count(),
                'active' => $active,
                'collected_mtd' => round($collected, 2),
                'outstanding' => round($due, 2),
            ];
        }

        $unassigned = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('area_id')
            ->count();
        if ($unassigned > 0) {
            $rows[] = [
                'area' => '(Unassigned)',
                'code' => '—',
                'total_customers' => $unassigned,
                'active' => Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('area_id')
                    ->where('status', CustomerStatus::ACTIVE)
                    ->count(),
                'collected_mtd' => 0,
                'outstanding' => 0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total_customers'] <=> $a['total_customers']);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packagePopularity(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(function (Package $pkg) use ($tenantId): array {
                $count = Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('package_id', $pkg->id)
                    ->count();
                $active = Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('package_id', $pkg->id)
                    ->where('status', CustomerStatus::ACTIVE)
                    ->count();

                $mrr = round($active * (float) $pkg->price_monthly, 2);

                return [
                    'package' => $pkg->name,
                    'speed' => $pkg->download_mbps.' Mbps',
                    'price' => (float) $pkg->price_monthly,
                    'subscribers' => $count,
                    'active' => $active,
                    'est_mrr' => $mrr,
                ];
            })
            ->sortByDesc('active')
            ->values()
            ->all();
    }

    private function totalCollected(Carbon $from, Carbon $to, int $tenantId): float
    {
        return round((float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount'), 2);
    }

    private function totalInvoiced(Carbon $from, Carbon $to, int $tenantId): float
    {
        return round((float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total'), 2);
    }

    private function totalOutstanding(int $tenantId): float
    {
        return round((float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->whereRaw('(total - amount_paid) > 0')
            ->sum(DB::raw('total - amount_paid')), 2);
    }

    /**
     * Zone-wise collection, billing & recovery for a date range.
     *
     * @return list<array<string, mixed>>
     */
    public function zoneCollectionReport(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $zones = Zone::withoutGlobalScopes()
            ->with('area:id,name')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($zones as $zone) {
            $customerIds = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('zone_id', $zone->id)
                ->pluck('id');

            $total = $customerIds->count();
            if ($total === 0) {
                continue;
            }

            $active = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('zone_id', $zone->id)
                ->where('status', CustomerStatus::ACTIVE)
                ->count();

            $invoiced = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('customer_id', $customerIds)
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->sum('total');

            $collected = (float) Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereIn('customer_id', $customerIds)
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount');

            $outstanding = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')
                ->sum(DB::raw('total - amount_paid'));

            $rows[] = [
                'area' => $zone->area?->name ?? '—',
                'zone' => $zone->name,
                'subscribers' => $total,
                'active' => $active,
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'outstanding' => round($outstanding, 2),
                'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
            ];
        }

        $unassignedIds = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('zone_id')
            ->pluck('id');

        if ($unassignedIds->isNotEmpty()) {
            $invoiced = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('customer_id', $unassignedIds)
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->sum('total');

            $collected = (float) Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereIn('customer_id', $unassignedIds)
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount');

            $rows[] = [
                'area' => '—',
                'zone' => '(No zone)',
                'subscribers' => $unassignedIds->count(),
                'active' => Customer::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('zone_id')
                    ->where('status', CustomerStatus::ACTIVE)
                    ->count(),
                'invoiced' => round($invoiced, 2),
                'collected' => round($collected, 2),
                'outstanding' => 0,
                'collection_rate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['collected'] <=> $a['collected']);

        return $rows;
    }

    /**
     * Churn counts grouped by zone / area in period.
     *
     * @return array{
     *     by_zone: list<array<string, mixed>>,
     *     totals: array{churned: int, suspended: int, expired: int},
     *     recent: list<array<string, mixed>>
     * }
     */
    public function churnByZoneReport(Carbon $from, Carbon $to, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $churnStatuses = [CustomerStatus::SUSPENDED, CustomerStatus::TERMINATED, CustomerStatus::EXPIRED];

        $byZone = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $churnStatuses)
            ->whereBetween('updated_at', [$from, $to])
            ->with(['zone.area', 'area', 'package:id,name'])
            ->get()
            ->groupBy(fn (Customer $c): string => (string) ($c->zone_id ?? 0))
            ->map(function ($group, $zoneId) use ($tenantId): array {
                /** @var Customer $first */
                $first = $group->first();

                return [
                    'area' => $first->zone?->area?->name ?? ($first->area?->name ?? '—'),
                    'zone' => $first->zone?->name ?? '(No zone)',
                    'churned' => $group->count(),
                    'suspended' => $group->where('status', CustomerStatus::SUSPENDED)->count(),
                    'terminated' => $group->where('status', CustomerStatus::TERMINATED)->count(),
                    'expired' => $group->where('status', CustomerStatus::EXPIRED)->count(),
                ];
            })
            ->values()
            ->sortByDesc('churned')
            ->values()
            ->all();

        $recent = Customer::withoutGlobalScopes()
            ->with(['zone.area', 'area', 'package'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $churnStatuses)
            ->whereBetween('updated_at', [$from, $to])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Customer $c): array => [
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'status' => $c->statusLabel(),
                'zone' => $c->zone?->name ?? '—',
                'area' => $c->zone?->area?->name ?? $c->area?->name ?? '—',
                'package' => $c->package?->name ?? '—',
                'updated_at' => $c->updated_at?->format('d M Y'),
            ])
            ->all();

        $all = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $churnStatuses)
            ->whereBetween('updated_at', [$from, $to]);

        return [
            'by_zone' => $byZone,
            'totals' => [
                'churned' => (clone $all)->count(),
                'suspended' => (clone $all)->where('status', CustomerStatus::SUSPENDED)->count(),
                'terminated' => (clone $all)->where('status', CustomerStatus::TERMINATED)->count(),
                'expired' => (clone $all)->where('status', CustomerStatus::EXPIRED)->count(),
            ],
            'recent' => $recent,
        ];
    }
}
