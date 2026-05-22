<?php

namespace App\Filament\Support;

use App\Models\Warehouse;
use App\Services\Inventory\WarehouseResolver;
use Filament\Forms\Components\Select;

final class InventoryWarehouseSelect
{
    public static function make(string $name = 'warehouse_id'): Select
    {
        return Select::make($name)
            ->label('Warehouse')
            ->options(function (): array {
                $tenantId = (int) auth()->user()?->tenant_id;
                app(WarehouseResolver::class)->defaultWarehouse($tenantId);

                return Warehouse::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->displayLabel()])
                    ->all();
            })
            ->default(fn (): int => app(WarehouseResolver::class)->defaultWarehouseId((int) auth()->user()->tenant_id))
            ->searchable()
            ->native(false);
    }
}
