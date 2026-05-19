<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
use App\Support\BillingCycleType;
use Carbon\Carbon;

final class ServiceExpiryExtensionService
{
    /**
     * Extend service_expires_at by one billing cycle from the later of now or current expiry.
     */
    public function extendForPaidCycle(Customer $customer): void
    {
        $package = $customer->package;
        if (! $package instanceof Package) {
            return;
        }

        $days = $this->cycleDays($package);
        if ($days <= 0) {
            return;
        }

        $base = $customer->service_expires_at && $customer->service_expires_at->isFuture()
            ? $customer->service_expires_at->copy()->startOfDay()
            : now()->startOfDay();

        $customer->forceFill([
            'service_expires_at' => $base->addDays($days)->toDateString(),
            'status' => 'active',
        ])->saveQuietly();
    }

    public function cycleDays(Package $package): int
    {
        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;
        $cycleDays = max(1, (int) ($package->billing_cycle_days ?: 30));

        return match ($type) {
            BillingCycleType::HOURLY => 1,
            BillingCycleType::DAILY => 1,
            BillingCycleType::DAYS_30 => $cycleDays,
            BillingCycleType::QUARTERLY => 90,
            BillingCycleType::HALF_YEARLY => 182,
            BillingCycleType::YEARLY => 365,
            default => (int) now()->daysInMonth,
        };
    }
}
