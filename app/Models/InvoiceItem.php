<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item): void {
            $item->line_total = round((float) $item->quantity * (float) $item->unit_price, 2);
        });
    }

    protected $fillable = [
        'invoice_id',
        'product_id',
        'device_id',
        'warehouse_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'sort_order',
        'meta',
        'stock_issued',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'meta' => 'array',
            'stock_issued' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
