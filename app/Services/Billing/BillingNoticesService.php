<?php

namespace App\Services\Billing;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Billing notice board for Code Pagol — overdue / due soon / high due.
 * Read-only; uses existing billing_infos + customers_infos (no schema change).
 */
final class BillingNoticesService
{
    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   sections: list<array<string, mixed>>,
     *   updated_at: string
     * }
     */
    public function payload(int $dueSoonDays = 3, int $limit = 50): array
    {
        $today = Carbon::today();
        $soonEnd = $today->copy()->addDays(max(1, $dueSoonDays));

        $base = BillingInfo::query()
            ->join('customers_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereNotIn('customers_infos.status', ['deleted'])
            ->select([
                'billing_infos.*',
                'customers_infos.customer_name',
                'customers_infos.customer_unique_id',
                'customers_infos.status as customer_status',
                'customers_infos.mobile',
                'customers_infos.ppp_user_id',
            ]);

        $overdue = (clone $base)
            ->whereNotNull('billing_infos.auto_disable_date')
            ->whereDate('billing_infos.auto_disable_date', '<', $today)
            ->where(function ($q) {
                $q->where('billing_infos.due_amount', '>', 0)
                    ->orWhere('customers_infos.status', 'disable')
                    ->orWhere('customers_infos.status', 'inactive');
            })
            ->orderBy('billing_infos.auto_disable_date')
            ->limit($limit)
            ->get();

        $dueSoon = (clone $base)
            ->whereNotNull('billing_infos.auto_disable_date')
            ->whereDate('billing_infos.auto_disable_date', '>=', $today)
            ->whereDate('billing_infos.auto_disable_date', '<=', $soonEnd)
            ->where('customers_infos.status', 'active')
            ->orderBy('billing_infos.auto_disable_date')
            ->limit($limit)
            ->get();

        $highDue = (clone $base)
            ->where('billing_infos.due_amount', '>', 0)
            ->whereIn('customers_infos.status', ['active', 'pending', 'disable'])
            ->orderByDesc('billing_infos.due_amount')
            ->limit($limit)
            ->get();

        $lateFeeRows = app(LateFeeService::class)->candidates($limit);

        $sections = array_values(array_filter([
            $this->section('overdue', __('Overdue / expired disable date'), __('Past auto-disable date — collect or Net OFF per policy.'), 'danger', $overdue),
            $this->section('due_soon', __('Due soon'), __('Auto-disable within :days days.', ['days' => $dueSoonDays]), 'warning', $dueSoon),
            $this->section('high_due', __('Highest due amounts'), __('Top open dues (active/pending/disabled).'), 'info', $highDue),
            $this->lateFeeSection($lateFeeRows),
        ]));

        return [
            'updated_at' => now()->toIso8601String(),
            'summary' => [
                'overdue' => $overdue->count(),
                'due_soon' => $dueSoon->count(),
                'high_due' => $highDue->count(),
                'late_fee' => $lateFeeRows->count(),
                'total_due_amount' => (float) $highDue->sum(fn ($r) => (float) $r->due_amount),
                'total' => $overdue->count() + $dueSoon->count() + $highDue->count() + $lateFeeRows->count(),
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @param  Collection<int, BillingInfo>  $rows
     * @return array<string, mixed>|null
     */
    private function section(string $key, string $title, string $hint, string $severity, Collection $rows): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $items = $rows->map(function ($row) use ($key) {
            $uid = (string) $row->customer_unique_id;
            $editUrl = null;
            try {
                $editUrl = route('customers.edit', encrypt($uid));
            } catch (\Throwable) {
                $editUrl = null;
            }

            return [
                'id' => $key.'-'.$row->id,
                'customer_name' => $row->customer_name ?: '—',
                'customer_unique_id' => $uid,
                'mobile' => $row->mobile,
                'status' => $row->customer_status,
                'due_amount' => (float) ($row->due_amount ?? 0),
                'monthly_rent' => (float) ($row->monthly_rent ?? 0),
                'auto_disable_date' => $row->auto_disable_date
                    ? Carbon::parse($row->auto_disable_date)->format('d M Y')
                    : '—',
                'edit_url' => $editUrl,
            ];
        })->values()->all();

        return [
            'key' => $key,
            'title' => $title,
            'hint' => $hint,
            'severity' => $severity,
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>|null
     */
    private function lateFeeSection(Collection $rows): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $rate = app(LateFeeService::class)->dailyRate();

        $items = $rows->map(function ($row) {
            $uid = (string) $row->customer_unique_id;
            try {
                $editUrl = route('customers.edit', encrypt($uid));
            } catch (\Throwable) {
                $editUrl = null;
            }

            return [
                'id' => 'late-'.$row->id,
                'customer_name' => $row->customer_name ?: '—',
                'customer_unique_id' => $uid,
                'mobile' => $row->mobile,
                'status' => $row->customer_status,
                'due_amount' => (float) ($row->due_amount ?? 0),
                'monthly_rent' => (float) ($row->monthly_rent ?? 0),
                'auto_disable_date' => $row->auto_disable_date
                    ? Carbon::parse($row->auto_disable_date)->format('d M Y')
                    : '—',
                'days_late' => (int) ($row->days_late ?? 0),
                'suggested_late_fee' => (float) ($row->suggested_late_fee ?? 0),
                'edit_url' => $editUrl,
            ];
        })->values()->all();

        return [
            'key' => 'late_fee',
            'title' => __('Suggested late fees'),
            'hint' => __('Advisory only — :rate BDT/day after grace (apply manually in collection).', ['rate' => number_format($rate, 2)]),
            'severity' => 'warning',
            'items' => $items,
        ];
    }
}
