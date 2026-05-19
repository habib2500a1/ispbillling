<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use BelongsToTenant;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_FIRST_MONTH_PERCENT = 'first_month_percent';

    protected $fillable = [
        'tenant_id',
        'code',
        'discount_type',
        'value',
        'max_uses',
        'uses_count',
        'min_invoice_amount',
        'valid_from',
        'valid_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_invoice_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isValidAt(\Carbon\CarbonInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->valid_from && $date->toDateString() < $this->valid_from->toDateString()) {
            return false;
        }

        if ($this->valid_to && $date->toDateString() > $this->valid_to->toDateString()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
