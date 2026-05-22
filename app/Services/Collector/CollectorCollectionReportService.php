<?php

namespace App\Services\Collector;

use App\Models\CollectorCollection;
use App\Models\Payment;
use App\Models\User;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CollectorCollectionReportService
{
    /**
     * @return array{total: float, count: int, by_collector: list<array{collector_id: int, name: string, total: float, count: int}>}
     */
    public function todaySummary(?int $tenantId = null, ?Carbon $date = null): array
    {
        $tenantId ??= TenantResolver::requiredTenantId();
        $date = ($date ?? now())->toDateString();

        $rows = CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('collected_at', $date)
            ->selectRaw('collector_id, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('collector_id')
            ->orderByDesc('total')
            ->get();

        $collectorIds = $rows->pluck('collector_id')->all();
        $names = User::query()->whereIn('id', $collectorIds)->pluck('name', 'id');

        $byCollector = $rows->map(fn ($row): array => [
            'collector_id' => (int) $row->collector_id,
            'name' => (string) ($names[$row->collector_id] ?? 'Staff #'.$row->collector_id),
            'total' => round((float) $row->total, 2),
            'count' => (int) $row->cnt,
        ])->all();

        return [
            'total' => round((float) array_sum(array_column($byCollector, 'total')), 2),
            'count' => (int) $rows->sum('cnt'),
            'by_collector' => $byCollector,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentCollections(int $limit = 25, ?int $collectorId = null, ?int $tenantId = null): Collection
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        $q = CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with([
                'customer:id,name,customer_code',
                'collector:id,name',
                'payment:id,receipt_number,meta,recorded_by',
                'inventorySale:id,sale_number,customer_name',
            ])
            ->orderByDesc('collected_at')
            ->limit($limit);

        if ($collectorId !== null) {
            $q->where('collector_id', $collectorId);
        }

        return $q->get()->map(function (CollectorCollection $row): array {
            $meta = is_array($row->payment?->meta) ? $row->payment->meta : [];
            $enteredByName = $meta['entered_by_name'] ?? null;
            if ($enteredByName === null && isset($meta['entered_by'])) {
                $enteredByName = User::query()->find($meta['entered_by'])?->name;
            }

            return [
                'id' => $row->id,
                'collected_at' => $row->collected_at?->format('Y-m-d H:i'),
                'collector_name' => $row->collector?->name ?? '—',
                'entered_by_name' => $enteredByName,
                'customer_name' => $row->customer?->name
                    ?? $row->inventorySale?->customer_name
                    ?? ($row->inventory_sale_id ? 'Retail sale' : '—'),
                'customer_code' => $row->customer?->customer_code,
                'amount' => round((float) $row->amount, 2),
                'method' => $row->payment_method,
                'receipt' => $row->payment?->receipt_number
                    ?? $row->inventorySale?->sale_number,
                'status' => $row->status,
            ];
        });
    }

    /**
     * Cash collections today for a single collector (from payments if collection row missing).
     */
    public function collectorTodayTotal(int $collectorId, ?int $tenantId = null): float
    {
        $tenantId ??= TenantResolver::requiredTenantId();

        $fromCollections = (float) CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('collector_id', $collectorId)
            ->whereDate('collected_at', today())
            ->sum('amount');

        if ($fromCollections > 0) {
            return round($fromCollections, 2);
        }

        return round((float) Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('recorded_by', $collectorId)
            ->where('status', 'completed')
            ->whereDate('paid_at', today())
            ->whereIn('method', config('collector.cash_methods', ['cash', 'counter']))
            ->sum('amount'), 2);
    }
}
