<?php

namespace App\Services\Billing;

use App\Models\Customer;
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
        $tenantId = TenantResolver::currentTenantId() ?? 0;
        $today = now()->toDateString();

        return Cache::remember(
            "billing_account_list_counts:{$tenantId}:{$today}",
            60,
            function () use ($today): array {
                $base = Customer::query();
                $notTerminated = fn () => (clone $base)->where('status', '!=', CustomerStatus::TERMINATED);

                return [
                    'all' => $notTerminated()->count(),
                    'active' => (clone $base)->where('status', CustomerStatus::ACTIVE)->count(),
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
                    'expired' => $notTerminated()
                        ->where(function ($q): void {
                            $q->where('status', CustomerStatus::EXPIRED)
                                ->orWhere(function ($q2): void {
                                    $q2->whereNotNull('service_expires_at')
                                        ->whereDate('service_expires_at', '<', now()->toDateString());
                                });
                        })->count(),
                    'pending' => $notTerminated()
                        ->where(function ($q): void {
                            $q->where('kyc_status', 'pending')
                                ->orWhereRaw("COALESCE(meta->>'installation_status', '') = ?", ['pending']);
                        })->count(),
                    'suspended' => (clone $base)->where('status', CustomerStatus::SUSPENDED)->count(),
                    'left' => (clone $base)->where('status', CustomerStatus::TERMINATED)->count(),
                ];
            },
        );
    }

    public function get(string $key): int
    {
        return $this->all()[$key] ?? 0;
    }
}
