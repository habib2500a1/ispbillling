<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

final class BillingInvoiceCounts
{
    /**
     * @return array<string, int>
     */
    public function all(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            "billing_sidebar_counts:{$tenantId}:".now()->format('Y-m-d-H'),
            60,
            fn (): array => $this->compute($tenantId),
        );
    }

    /**
     * @return array<string, int>
     */
    private function compute(int $tenantId): array
    {
        $base = Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $today = now()->toDateString();

        return [
            'all' => (clone $base)->whereNotIn('status', ['void', 'cancelled'])->count(),
            'due' => (clone $base)->whereIn('status', ['open', 'partial', 'draft'])->count(),
            'paid' => (clone $base)->where('status', 'paid')->count(),
            'today_collection' => Payment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereDate('paid_at', $today)
                ->count(),
        ];
    }
}
