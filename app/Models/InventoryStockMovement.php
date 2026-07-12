<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockMovement extends Model
{
    public const TYPES = [
        'in' => 'Stock in',
        'out' => 'Stock out',
        'adjust' => 'Adjust',
    ];

    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'inventory_product_id',
        'type',
        'quantity',
        'stock_after',
        'unit_cost',
        'reference',
        'notes',
        'staff_user_id',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'moved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'inventory_product_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
