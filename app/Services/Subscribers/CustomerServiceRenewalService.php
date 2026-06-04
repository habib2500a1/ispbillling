<?php

namespace App\Services\Subscribers;

use App\Models\Customer;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CustomerServiceRenewalService
{
    /**
     * Extend service expiry and re-enable network (admin: any 1–730 days).
     *
     * @return array{expires_at: string, days: int}
     */
    public function extendDays(Customer $customer, int $days, bool $syncNetwork = true): array
    {
        $days = max(1, min(730, $days));

        $base = $customer->service_expires_at && $customer->service_expires_at->isFuture()
            ? $customer->service_expires_at->copy()->startOfDay()
            : now()->startOfDay();

        $expiresAt = $base->copy()->addDays($days)->toDateString();

        Customer::withoutEvents(function () use ($customer, $expiresAt): void {
            $customer->forceFill([
                'service_expires_at' => $expiresAt,
            ])->saveQuietly();
        });

        $this->afterValidityExtended($customer->fresh() ?? $customer, $syncNetwork);

        $fresh = $customer->fresh();

        return [
            'expires_at' => (string) $fresh?->service_expires_at?->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * Extend service until a chosen calendar date (inclusive).
     *
     * @return array{expires_at: string, days: int}
     */
    public function extendUntil(Customer $customer, CarbonInterface $until, bool $syncNetwork = true): array
    {
        $until = Carbon::parse($until)->startOfDay();
        $base = $customer->service_expires_at && $customer->service_expires_at->isFuture()
            ? $customer->service_expires_at->copy()->startOfDay()
            : now()->startOfDay();

        if ($until->lte($base)) {
            throw new \InvalidArgumentException('New validity must be after today or the current expiry date.');
        }

        $days = (int) $base->diffInDays($until);

        return $this->extendDays($customer, max(1, $days), $syncNetwork);
    }

    public static function suggestedValidUntil(Customer $customer): Carbon
    {
        $base = $customer->service_expires_at && $customer->service_expires_at->isFuture()
            ? $customer->service_expires_at->copy()->startOfDay()
            : now()->startOfDay();

        return $base->copy()->addDays(5);
    }

    /**
     * After validity moves forward (quick extend, edit form, expire day): panel active + MikroTik ON.
     */
    public function afterValidityExtended(Customer $customer, bool $syncNetwork = true): void
    {
        $customer = $customer->fresh() ?? $customer;

        if (! $customer->isServiceExpired()) {
            $updates = [];
            $status = CustomerStatus::normalize((string) $customer->status);

            if ($status === CustomerStatus::EXPIRED || $status === CustomerStatus::SUSPENDED) {
                $updates['status'] = CustomerStatus::ACTIVE;
            }

            if (($customer->network_access_state ?? 'active') === 'suspended') {
                $updates['network_access_state'] = 'active';
            }

            if ($updates !== []) {
                $customer->forceFill($updates)->saveQuietly();
                $customer = $customer->fresh() ?? $customer;
            }
        }

        if ($syncNetwork) {
            CustomerNetworkSync::forceNetOn($customer);
        }
    }
}
