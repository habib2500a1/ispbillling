<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Payment;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Monthly new lines + renewals for the admin dashboard (5–6 month charts).
 */
final class SubscriberLifecycleDashboardService
{
    public const CHART_MONTHS = 6;

    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null, int $months = self::CHART_MONTHS): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $months = max(2, min(12, $months));

        return Cache::remember(
            "dashboard:subscriber_lifecycle:v1:{$tenantId}:{$months}:".now()->format('Y-m-d-H'),
            now()->addMinutes(3),
            fn (): array => $this->build($tenantId, $months),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId, int $months): array
    {
        $newLines = $this->monthlyNewLines($tenantId, $months);
        $renewals = $this->monthlyRenewals($tenantId, $months);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $today = today();

        return [
            'months' => $months,
            'range_label' => $newLines['range_label'],
            'new_lines' => $newLines,
            'renewals' => $renewals,
            'mtd_new_lines' => $this->countNewLines($tenantId, $monthStart, $monthEnd),
            'mtd_renewals' => $this->countRenewals($tenantId, $monthStart, $monthEnd),
            'today_new_lines' => $this->countNewLines($tenantId, $today->copy()->startOfDay(), $today->copy()->endOfDay()),
            'today_renewals' => $this->countRenewals($tenantId, $today->copy()->startOfDay(), $today->copy()->endOfDay()),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, max: int, months: int, range_label: string, total: int}
     */
    public function monthlyNewLines(int $tenantId, int $months): array
    {
        $labels = [];
        $values = [];
        $start = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $labels[] = $monthStart->format('M');
            $values[] = $this->countNewLines($tenantId, $monthStart, $monthEnd);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'max' => max(1, ...$values),
            'months' => $months,
            'range_label' => $start->format('M Y').' – '.now()->format('M Y'),
            'total' => array_sum($values),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, max: int, months: int, range_label: string, total: int}
     */
    public function monthlyRenewals(int $tenantId, int $months): array
    {
        $labels = [];
        $values = [];
        $start = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $labels[] = $monthStart->format('M');
            $values[] = $this->countRenewals($tenantId, $monthStart, $monthEnd);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'max' => max(1, ...$values),
            'months' => $months,
            'range_label' => $start->format('M Y').' – '.now()->format('M Y'),
            'total' => array_sum($values),
        ];
    }

    private function countNewLines(int $tenantId, Carbon $from, Carbon $to): int
    {
        return (int) Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('joined_at', '>=', $from->toDateString())
            ->whereDate('joined_at', '<=', $to->toDateString())
            ->count();
    }

    private function countRenewals(int $tenantId, Carbon $from, Carbon $to): int
    {
        $existingCustomerIds = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('joined_at', '<', $from->toDateString())
            ->pluck('id');

        if ($existingCustomerIds->isEmpty()) {
            return 0;
        }

        return (int) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY, PaymentType::PREPAY])
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('customer_id', $existingCustomerIds)
            ->count();
    }

}
