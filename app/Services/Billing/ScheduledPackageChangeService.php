<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

final class ScheduledPackageChangeService
{
    /**
     * Schedule downgrade (or any change) at start of next billing period.
     */
    public function scheduleForNextCycle(Customer $customer, Package $newPackage): CarbonInterface
    {
        $current = $customer->package;
        if ($current === null) {
            $effective = now()->startOfDay();
        } else {
            [, $periodEnd] = BillingPeriodResolver::resolve($current, now());
            $effective = $periodEnd->copy()->addDay()->startOfDay();
        }

        $customer->forceFill([
            'pending_package_id' => $newPackage->id,
            'pending_package_effective_date' => $effective->toDateString(),
        ])->save();

        return $effective;
    }

    public function clearSchedule(Customer $customer): void
    {
        $customer->forceFill([
            'pending_package_id' => null,
            'pending_package_effective_date' => null,
        ])->save();
    }

    /**
     * @return array{applied: int, skipped: int}
     */
    public function applyDueChanges(?int $tenantId = null, bool $dryRun = false): array
    {
        $stats = ['applied' => 0, 'skipped' => 0];

        $query = Customer::query()
            ->withoutGlobalScopes()
            ->whereNotNull('pending_package_id')
            ->whereNotNull('pending_package_effective_date')
            ->whereDate('pending_package_effective_date', '<=', now()->toDateString())
            ->with(['package', 'pendingPackage']);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        foreach ($query->cursor() as $customer) {
            $package = $customer->pendingPackage;
            if (! $package instanceof Package || ! $package->is_active) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                $stats['applied']++;

                continue;
            }

            $from = $customer->package?->name ?? '—';
            $customer->forceFill([
                'package_id' => $package->id,
                'pending_package_id' => null,
                'pending_package_effective_date' => null,
            ])->save();

            Log::info('billing.package_change.applied', [
                'customer_id' => $customer->id,
                'from' => $from,
                'to' => $package->name,
            ]);

            $stats['applied']++;
        }

        return $stats;
    }
}
