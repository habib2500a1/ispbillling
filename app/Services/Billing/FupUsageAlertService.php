<?php

namespace App\Services\Billing;

use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Package;
use App\Services\Notifications\NotificationDispatcher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class FupUsageAlertService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @return array{warnings: int, critical: int, skipped: int}
     */
    public function run(?int $tenantId = null, bool $dryRun = false): array
    {
        if (! config('billing.fup_alerts.enabled', true)) {
            return ['warnings' => 0, 'critical' => 0, 'skipped' => 0];
        }

        $stats = ['warnings' => 0, 'critical' => 0, 'skipped' => 0];

        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('package_id')
            ->with('package');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        foreach ($query->cursor() as $customer) {
            $package = $customer->package;
            if (! $package instanceof Package) {
                continue;
            }

            $usage = $this->periodUsagePercent($customer, $package);
            if ($usage === null) {
                continue;
            }

            $warnAt = (float) config('billing.fup_alerts.warn_percent', 80);
            $criticalAt = (float) config('billing.fup_alerts.critical_percent', 100);

            $event = null;
            if ($usage['percent'] >= $criticalAt) {
                $event = 'fup_critical';
            } elseif ($usage['percent'] >= $warnAt) {
                $event = 'fup_warning';
            }

            if ($event === null) {
                continue;
            }

            $cacheKey = "fup_alert:{$event}:{$customer->id}:".now()->format('Y-m-d');
            if (Cache::has($cacheKey)) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $event === 'fup_critical' ? $stats['critical']++ : $stats['warnings']++;

                continue;
            }

            if (! $this->eventEnabled($event)) {
                $stats['skipped']++;

                continue;
            }

            $this->dispatcher->notifyCustomer($customer, $event, [
                'gb_used' => number_format($usage['gb_used'], 2),
                'gb_allowed' => number_format($usage['gb_allowed'], 2),
                'percent' => number_format($usage['percent'], 0),
                'period_end' => $usage['period_end'],
            ], [
                'subject' => $event === 'fup_critical' ? 'Data limit reached' : 'Data usage warning',
            ]);

            Cache::put($cacheKey, true, now()->endOfDay());
            $event === 'fup_critical' ? $stats['critical']++ : $stats['warnings']++;
        }

        return $stats;
    }

    /**
     * @return array{gb_used: float, gb_allowed: float, percent: float, period_end: string}|null
     */
    public function periodUsagePercent(Customer $customer, Package $package): ?array
    {
        $dailyQuotaGb = (float) ($package->included_data_gb ?? 0);
        if ($dailyQuotaGb <= 0) {
            return null;
        }

        $today = now()->startOfDay();
        [$periodStart, $periodEnd] = BillingPeriodResolver::resolve($package, $today);

        if ($today->lt($periodStart) || $today->gt($periodEnd)) {
            return null;
        }

        $daysElapsed = max(1, $periodStart->diffInDays($today) + 1);
        $periodDays = max(1, $periodStart->diffInDays($periodEnd) + 1);
        $allowedGb = $dailyQuotaGb * $daysElapsed;

        $usedBytes = (int) BandwidthUsageDaily::query()
            ->where('customer_id', $customer->id)
            ->whereDate('usage_date', '>=', $periodStart->toDateString())
            ->whereDate('usage_date', '<=', $today->toDateString())
            ->selectRaw('COALESCE(SUM(bytes_in + bytes_out), 0) as total')
            ->value('total');

        $usedGb = $usedBytes / 1073741824;
        if ($allowedGb <= 0) {
            return null;
        }

        $percent = ($usedGb / $allowedGb) * 100;

        return [
            'gb_used' => round($usedGb, 2),
            'gb_allowed' => round($allowedGb, 2),
            'percent' => round($percent, 1),
            'period_end' => $periodEnd->format('d M Y'),
        ];
    }

    private function eventEnabled(string $event): bool
    {
        return (bool) config("notifications.events.{$event}.enabled", true);
    }
}
