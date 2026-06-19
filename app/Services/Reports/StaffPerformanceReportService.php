<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Collector\CollectorStaffResolver;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Staff collection + new-line KPIs (legacy portal received_by + local recorded_by).
 */
final class StaffPerformanceReportService
{
    public function __construct(
        private readonly CollectorStaffResolver $collectorResolver,
    ) {}

    /**
     * @return array{
     *     today: array{total: float, count: int, by_staff: list<array{staff_id: int|null, name: string, total: float, count: int}>},
     *     month: array{total: float, count: int, by_staff: list<array{staff_id: int|null, name: string, total: float, count: int}>},
     *     new_lines_month: array{total: int, by_staff: list<array{staff_id: int|null, name: string, count: int}>},
     *     mine: array{today_collection: float, month_collection: float, month_new_lines: int}|null
     * }
     */
    public function dashboard(int $tenantId, ?int $scopedStaffId = null): array
    {
        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();
        $monthFrom = now()->startOfMonth();
        $monthTo = now()->endOfMonth();

        $today = $this->collectionSummary($tenantId, $todayFrom, $todayTo, $scopedStaffId);
        $month = $this->collectionSummary($tenantId, $monthFrom, $monthTo, $scopedStaffId);
        $newLines = $this->newLinesSummary($tenantId, $monthFrom, $monthTo, $scopedStaffId);

        $mine = null;
        if ($scopedStaffId !== null && $scopedStaffId > 0) {
            $mine = [
                'today_collection' => $this->staffTotalFromRows($today['by_staff'], $scopedStaffId),
                'month_collection' => $this->staffTotalFromRows($month['by_staff'], $scopedStaffId),
                'month_new_lines' => $this->staffCountFromRows($newLines['by_staff'], $scopedStaffId),
            ];
        }

        return [
            'today' => $today,
            'month' => $month,
            'new_lines_month' => $newLines,
            'mine' => $mine,
        ];
    }

    public function todayCollectionTotal(int $tenantId): float
    {
        return $this->collectionSummary(
            $tenantId,
            now()->startOfDay(),
            now()->endOfDay(),
        )['total'];
    }

    /**
     * @return array{total: float, count: int, by_staff: list<array{staff_id: int|null, name: string, total: float, count: int}>}
     */
    public function collectionSummary(
        int $tenantId,
        Carbon $from,
        Carbon $to,
        ?int $scopedStaffId = null,
    ): array {
        $payments = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY])
            ->whereBetween('paid_at', [$from, $to])
            ->get(['id', 'amount', 'recorded_by', 'meta']);

        $grouped = $this->groupPaymentsByStaff($payments, $tenantId);

        if ($scopedStaffId !== null && $scopedStaffId > 0) {
            $grouped = $grouped->filter(
                fn (array $row): bool => ($row['staff_id'] ?? null) === $scopedStaffId
            )->values();
        }

        $byStaff = $grouped
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => round((float) $grouped->sum('total'), 2),
            'count' => (int) $grouped->sum('count'),
            'by_staff' => $byStaff,
        ];
    }

    /**
     * @return array{total: int, by_staff: list<array{staff_id: int|null, name: string, count: int}>}
     */
    public function newLinesSummary(
        int $tenantId,
        Carbon $from,
        Carbon $to,
        ?int $scopedStaffId = null,
    ): array {
        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('joined_at', '>=', $from->toDateString())
            ->whereDate('joined_at', '<=', $to->toDateString())
            ->get(['id', 'meta']);

        $lookup = $this->collectorResolver->staffNameLookup($tenantId);
        $buckets = [];

        foreach ($customers as $customer) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            $staffId = isset($meta['registered_by_id']) ? (int) $meta['registered_by_id'] : null;
            $name = null;

            if ($staffId !== null && $staffId > 0) {
                $name = User::query()->find($staffId)?->name;
            }

            if ($name === null || $name === '') {
                $assigned = trim((string) ($meta['assigned_employee'] ?? ''));
                if ($assigned !== '') {
                    $key = mb_strtolower($assigned);
                    $staffId = $lookup[$key]['id'] ?? null;
                    $name = $lookup[$key]['label'] ?? $assigned;
                }
            }

            if ($name === null || $name === '') {
                $staffId = null;
                $name = 'Unassigned';
            }

            if ($scopedStaffId !== null && $scopedStaffId > 0 && $staffId !== $scopedStaffId) {
                continue;
            }

            $bucketKey = $staffId !== null ? 'id:'.$staffId : 'name:'.mb_strtolower($name);
            $buckets[$bucketKey] ??= [
                'staff_id' => $staffId,
                'name' => $name,
                'count' => 0,
            ];
            $buckets[$bucketKey]['count']++;
        }

        $rows = collect($buckets)->sortByDesc('count')->values()->all();

        return [
            'total' => array_sum(array_column($rows, 'count')),
            'by_staff' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, array{staff_id: int|null, name: string, total: float, count: int}>
     */
    private function groupPaymentsByStaff(Collection $payments, int $tenantId): Collection
    {
        $buckets = [];

        foreach ($payments as $payment) {
            $staffId = $this->collectorResolver->resolveCollectorUserIdFromPayment($payment);
            $name = $this->collectorResolver->resolveStaffDisplayNameFromPayment($payment, $tenantId);

            if ($name === null || $name === '') {
                $name = 'Online / unassigned';
            }

            $key = $staffId !== null ? 'id:'.$staffId : 'name:'.mb_strtolower($name);
            $buckets[$key] ??= [
                'staff_id' => $staffId,
                'name' => $name,
                'total' => 0.0,
                'count' => 0,
            ];
            $buckets[$key]['total'] += (float) $payment->amount;
            $buckets[$key]['count']++;
        }

        return collect($buckets)->map(function (array $row): array {
            $row['total'] = round($row['total'], 2);

            return $row;
        });
    }

    /**
     * @param  list<array{staff_id: int|null, name: string, total: float, count: int}>  $rows
     */
    private function staffTotalFromRows(array $rows, int $staffId): float
    {
        foreach ($rows as $row) {
            if (($row['staff_id'] ?? null) === $staffId) {
                return round((float) ($row['total'] ?? 0), 2);
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array{staff_id: int|null, name: string, count: int}>  $rows
     */
    private function staffCountFromRows(array $rows, int $staffId): int
    {
        foreach ($rows as $row) {
            if (($row['staff_id'] ?? null) === $staffId) {
                return (int) ($row['count'] ?? 0);
            }
        }

        return 0;
    }

    public function flushCache(int $tenantId): void
    {
        $this->collectorResolver->flushStaffNameLookup($tenantId);
    }
}
