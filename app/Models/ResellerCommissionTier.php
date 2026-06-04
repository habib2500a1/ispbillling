<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerCommissionTier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'min_amount',
        'max_amount',
        'commission_type',
        'commission_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function appliesTo(float $gross): bool
    {
        if ($gross < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $gross > (float) $this->max_amount) {
            return false;
        }

        return true;
    }

    public function calculate(float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        if ($this->commission_type === 'fixed') {
            return min($gross, (float) $this->commission_value);
        }

        return round($gross * ((float) $this->commission_value / 100), 2);
    }
}
