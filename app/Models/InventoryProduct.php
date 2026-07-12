<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryProduct extends Model
{
    public const CATEGORIES = [
        'onu' => 'ONU / ONT',
        'router' => 'Router',
        'cable' => 'Cable',
        'splitter' => 'Splitter',
        'power' => 'Power / Adapter',
        'accessory' => 'Accessory',
        'other' => 'Other',
    ];

    protected $table = 'inventory_products';

    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'stock_qty',
        'reorder_level',
        'cost_price',
        'sell_price',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'stock_qty' => 'integer',
        'reorder_level' => 'integer',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'inventory_product_id');
    }

    public function isLowStock(): bool
    {
        return $this->reorder_level > 0 && $this->stock_qty <= $this->reorder_level;
    }

    public function stockValue(): float
    {
        return round((float) $this->cost_price * (int) $this->stock_qty, 2);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: 'Other');
    }
}
