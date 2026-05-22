<?php

namespace App\Services\Inventory;

use App\Models\InventorySale;
use App\Models\User;
use App\Services\Collector\CollectorSettlementService;

/**
 * Staff retail cash sales → collector wallet (full amount) until admin settlement transfer.
 */
final class InventoryStaffCollectionService
{
    public function qualifies(InventorySale $sale): bool
    {
        if (! config('inventory.staff_sale_to_collector_wallet', true)) {
            return false;
        }

        if (! app(CollectorSettlementService::class)->isEnabled()) {
            return false;
        }

        if ($sale->recorded_by === null || $sale->status !== 'completed') {
            return false;
        }

        return in_array(
            (string) $sale->payment_method,
            config('inventory.staff_collector_cash_methods', ['cash', 'counter']),
            true,
        );
    }

    public function recordFromSale(InventorySale $sale): void
    {
        if (! $this->qualifies($sale)) {
            return;
        }

        app(CollectorSettlementService::class)->recordCollectionFromInventorySale(
            $sale->fresh(),
            User::query()->find($sale->recorded_by),
        );
    }
}
