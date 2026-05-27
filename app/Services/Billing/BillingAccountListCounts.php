<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Support\CustomerAccountScopes;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

final class BillingAccountListCounts
{
    /**
     * @return array<string, int>
     */
    public function all(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $today = now()->toDateString();

        return Cache::remember(
            "billing_account_list_counts:{$tenantId}:{$today}",
            60,
            function () use ($today, $tenantId): array {
                $base = Customer::query()->where('tenant_id', $tenantId);
                $notTerminated = fn () => (clone $base)->where('status', '!=', CustomerStatus::TERMINATED);

                return [
                    'all' => $notTerminated()->count(),
                    'active' => CustomerAccountScopes::applyActive(clone $base)->count(),
                    'today' => (clone $base)
                        ->whereDate('created_at', today())
                        ->count(),
                    'expire_3' => $notTerminated()
                        ->whereNotNull('service_expires_at')
                        ->whereDate('service_expires_at', '>=', $today)
                        ->whereDate('service_expires_at', '<=', now()->addDays(3)->toDateString())
                        ->count(),
                    'expire_7' => $notTerminated()
                        ->whereNotNull('service_expires_at')
                        ->whereDate('service_expires_at', '>=', $today)
                        ->whereDate('service_expires_at', '<=', now()->addDays(7)->toDateString())
                        ->count(),
                    'expired' => CustomerAccountScopes::applyExpired($notTerminated())->count(),
                    'pending' => $notTerminated()
                        ->where(function ($q): void {
                            $q->where('kyc_status', 'pending')
                                ->orWhereRaw("COALESCE(meta->>'installation_status', '') = ?", ['pending']);
                        })->count(),
                    'suspended' => (clone $base)->where('status', CustomerStatus::SUSPENDED)->count(),
                    'left' => CustomerAccountScopes::applyLeft(clone $base)->count(),
                ];
            },
        );
    }

    public function get(string $key): int
    {
        return $this->all()[$key] ?? 0;
    }
}
