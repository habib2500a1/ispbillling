<?php

namespace App\Filament\Support;

use App\Models\Warehouse;
use App\Services\Inventory\WarehouseResolver;
use App\Support\TenantResolver;
use Filament\Forms\Components\Select;

final class InventoryWarehouseSelect
{
    public static function make(string $name = 'warehouse_id'): Select
    {
        return Select::make($name)
            ->label('Warehouse')
            ->options(function (): array {
                $tenantId = TenantResolver::requiredTenantId();
                app(WarehouseResolver::class)->defaultWarehouse($tenantId);

                return Warehouse::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->displayLabel()])
                    ->all();
            })
            ->default(fn (): int => app(WarehouseResolver::class)->defaultWarehouseId(TenantResolver::requiredTenantId()))
            ->searchable()
            ->native(false);
    }
}
