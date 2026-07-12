<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPurchaseOrder extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    protected $table = 'inventory_purchase_orders';

    protected $fillable = [
        'po_number',
        'vendor_name',
        'warehouse_id',
        'status',
        'total',
        'ordered_at',
        'received_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'ordered_at' => 'date',
        'received_at' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrderItem::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['draft', 'ordered'], true);
    }
}
