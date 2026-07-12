<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySale extends Model
{
    public const CHANNELS = [
        'counter' => 'Counter sale',
        'issue' => 'Issue to customer',
        'field' => 'Field issue',
    ];

    protected $table = 'inventory_sales';

    protected $fillable = [
        'sale_number',
        'channel',
        'customer_unique_id',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount',
        'total',
        'total_cost',
        'gross_profit',
        'payment_method',
        'status',
        'notes',
        'recorded_by',
        'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InventorySaleItem::class, 'inventory_sale_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_unique_id', 'customer_unique_id');
    }

    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? ucfirst((string) $this->channel);
    }
}
