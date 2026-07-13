<?php

namespace App\Services\Billing;

use App\Models\BillingInfo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Suggested late fees for overdue billing rows (read-only advisory).
 */
final class LateFeeService
{
    public function dailyRate(): float
    {
        return max(0, (float) siteUrlSettings('late_fee_per_day', 10));
    }

    public function graceDays(): int
    {
        return max(0, (int) siteUrlSettings('late_fee_grace_days', 0));
    }

    /**
     * @return Collection<int, object>
     */
    public function candidates(int $limit = 50): Collection
    {
        $today = Carbon::today();
        $rate = $this->dailyRate();
        $grace = $this->graceDays();

        if ($rate <= 0) {
            return collect();
        }

        return BillingInfo::query()
            ->join('customers_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->where('billing_infos.due_amount', '>', 0)
            ->whereNotNull('billing_infos.auto_disable_date')
            ->whereDate('billing_infos.auto_disable_date', '<', $today->copy()->subDays($grace))
            ->select([
                'billing_infos.*',
                'customers_infos.customer_name',
                'customers_infos.customer_unique_id',
                'customers_infos.mobile',
                'customers_infos.status as customer_status',
            ])
            ->orderByDesc('billing_infos.due_amount')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($today, $rate, $grace) {
                $expired = Carbon::parse($row->auto_disable_date);
                $daysLate = max(0, $expired->diffInDays($today) - $grace);
                $row->days_late = $daysLate;
                $row->suggested_late_fee = round($daysLate * $rate, 2);

                return $row;
            });
    }
}
