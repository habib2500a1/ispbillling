<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sku',
        'barcode',
        'name',
        'description',
        'unit',
        'unit_price',
        'cost_price',
        'sell_price',
        'last_purchase_cost',
        'stock_qty',
        'reorder_level',
        'is_active',
        'show_on_shop',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'last_purchase_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'show_on_shop' => 'boolean',
        ];
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function effectiveCost(): float
    {
        $cost = (float) ($this->cost_price ?? 0);
        if ($cost > 0.009) {
            return round($cost, 2);
        }
        if ($this->last_purchase_cost !== null && (float) $this->last_purchase_cost > 0) {
            return round((float) $this->last_purchase_cost, 2);
        }

        return round((float) ($this->unit_price ?? 0), 2);
    }

    public function effectiveSellPrice(): float
    {
        $sell = (float) ($this->sell_price ?? 0);
        if ($sell > 0.009) {
            return round($sell, 2);
        }

        return round((float) ($this->unit_price ?? 0), 2);
    }

    public function marginPerUnit(): float
    {
        return round(max(0, $this->effectiveSellPrice() - $this->effectiveCost()), 2);
    }

    public function stockValue(): float
    {
        return round((int) $this->stock_qty * $this->effectiveCost(), 2);
    }

    public function isLowStock(): bool
    {
        return (int) $this->reorder_level > 0 && (int) $this->stock_qty <= (int) $this->reorder_level;
    }
}
