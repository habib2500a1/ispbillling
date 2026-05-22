<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'sale_number',
        'channel',
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

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventorySaleItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function generateSaleNumber(int $tenantId): string
    {
        return 'SAL-'.now()->format('Ymd').'-'.str_pad(
            (string) ((int) static::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereDate('sold_at', today())->count() + 1),
            4,
            '0',
            STR_PAD_LEFT,
        );
    }
}
