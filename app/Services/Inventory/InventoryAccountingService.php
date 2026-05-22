<?php

namespace App\Services\Inventory;

use App\Models\InventorySale;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\ChartOfAccountSeeder;
use App\Services\Inventory\InventoryStaffCollectionService;

final class InventoryAccountingService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChartOfAccountSeeder $chartSeeder,
    ) {}

    public function postPurchaseReceive(PurchaseOrder $order): void
    {
        if (! config('inventory.auto_post_purchase_receive', true)) {
            return;
        }

        $existing = JournalEntry::withoutGlobalScopes()
            ->where('source_type', 'purchase_order_receive')
            ->where('source_id', $order->id)
            ->exists();

        if ($existing) {
            return;
        }

        $total = round((float) $order->total, 2);
        if ($total <= 0.009) {
            return;
        }

        $tenantId = (int) $order->tenant_id;
        $this->chartSeeder->seedForTenant($tenantId);

        $this->ledger->post(
            'Inventory received · '.$order->po_number,
            [
                ['account_code' => config('inventory.inventory_asset_code', '1300'), 'debit' => $total],
                ['account_code' => config('inventory.ap_account_code', '2000'), 'credit' => $total],
            ],
            $order->received_at ?? now(),
            'purchase_order_receive',
            (int) $order->id,
            $tenantId,
        );
    }

    public function postRetailSale(InventorySale $sale): void
    {
        if (! config('inventory.auto_post_retail_sale', true) || $sale->status !== 'completed') {
            return;
        }

        $existing = JournalEntry::withoutGlobalScopes()
            ->where('source_type', 'inventory_sale')
            ->where('source_id', $sale->id)
            ->exists();

        if ($existing) {
            return;
        }

        $total = round((float) $sale->total, 2);
        $cogs = round((float) $sale->total_cost, 2);

        if ($total <= 0.009) {
            return;
        }

        $tenantId = (int) $sale->tenant_id;
        $this->chartSeeder->seedForTenant($tenantId);

        $cashCode = $this->debitAccountForSale($sale);
        $revenueCode = config('inventory.retail_revenue_code', '4050');
        $cogsCode = config('inventory.cogs_account_code', '5050');
        $inventoryCode = config('inventory.inventory_asset_code', '1300');

        $lines = [
            ['account_code' => $cashCode, 'debit' => $total],
            ['account_code' => $revenueCode, 'credit' => $total],
        ];

        if ($cogs > 0.009) {
            $lines[] = ['account_code' => $cogsCode, 'debit' => $cogs];
            $lines[] = ['account_code' => $inventoryCode, 'credit' => $cogs];
        }

        $this->ledger->post(
            'Retail sale '.$sale->sale_number,
            $lines,
            $sale->sold_at ?? now(),
            'inventory_sale',
            (int) $sale->id,
            $tenantId,
        );
    }

    private function debitAccountForSale(InventorySale $sale): string
    {
        if (app(InventoryStaffCollectionService::class)->qualifies($sale)) {
            return (string) config('collector.holding_account_code', '1050');
        }

        $method = (string) $sale->payment_method;
        if (in_array($method, ['bkash', 'nagad', 'bank'], true)) {
            return (string) config('accounting.bank_account_code', '1100');
        }

        return (string) config('inventory.cash_account_code', '1000');
    }
}
