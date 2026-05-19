<?php

namespace App\Services\Billing;

use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use Carbon\CarbonInterface;

final class FupOverageBillingService
{
    /**
     * @return array{gb_used: float, gb_allowed: float, gb_over: float, amount: float, description: string}|null
     */
    public function calculateForPeriod(
        Customer $customer,
        Package $package,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): ?array {
        if (! config('billing.fup_overage_enabled', true)) {
            return null;
        }

        $dailyQuotaGb = (float) ($package->included_data_gb ?? 0);
        if ($dailyQuotaGb <= 0) {
            return null;
        }

        $pricePerGb = $this->pricePerGb($package);
        if ($pricePerGb <= 0) {
            return null;
        }

        if ($this->alreadyBilledForPeriod($customer, $periodStart, $periodEnd)) {
            return null;
        }

        $periodDays = max(1, $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1);
        $allowedBytes = (int) round($dailyQuotaGb * 1073741824 * $periodDays);

        $usedBytes = (int) BandwidthUsageDaily::query()
            ->where('customer_id', $customer->id)
            ->whereDate('usage_date', '>=', $periodStart->toDateString())
            ->whereDate('usage_date', '<=', $periodEnd->toDateString())
            ->selectRaw('COALESCE(SUM(bytes_in + bytes_out), 0) as total')
            ->value('total');

        if ($usedBytes <= $allowedBytes) {
            return null;
        }

        $overBytes = $usedBytes - $allowedBytes;
        $gbOver = round($overBytes / 1073741824, 2);
        $amount = round($gbOver * $pricePerGb, 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'gb_used' => round($usedBytes / 1073741824, 2),
            'gb_allowed' => round($allowedBytes / 1073741824, 2),
            'gb_over' => $gbOver,
            'amount' => $amount,
            'description' => sprintf(
                'Data overage — %.2f GB over %.2f GB allowance (%d days @ %.2f GB/day)',
                $gbOver,
                round($allowedBytes / 1073741824, 2),
                $periodDays,
                $dailyQuotaGb,
            ),
        ];
    }

    public function appendToInvoice(
        Invoice $invoice,
        Customer $customer,
        Package $package,
        int $sortOrder,
    ): int {
        $overage = $this->calculateForPeriod(
            $customer,
            $package,
            $invoice->period_start,
            $invoice->period_end,
        );

        if ($overage === null) {
            return $sortOrder;
        }

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'fup_overage',
            'description' => $overage['description'],
            'quantity' => 1,
            'unit_price' => $overage['amount'],
            'line_total' => 0,
            'sort_order' => $sortOrder,
            'meta' => [
                'gb_used' => $overage['gb_used'],
                'gb_allowed' => $overage['gb_allowed'],
                'gb_over' => $overage['gb_over'],
            ],
        ]);

        return $sortOrder + 1;
    }

    private function pricePerGb(Package $package): float
    {
        if ($package->overage_price_per_gb !== null && (float) $package->overage_price_per_gb > 0) {
            return (float) $package->overage_price_per_gb;
        }

        return (float) config('billing.fup_overage_price_per_gb', 10);
    }

    private function alreadyBilledForPeriod(
        Customer $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): bool {
        return InvoiceItem::query()
            ->where('item_type', 'fup_overage')
            ->whereHas('invoice', fn ($q) => $q
                ->where('customer_id', $customer->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString()))
            ->exists();
    }
}
