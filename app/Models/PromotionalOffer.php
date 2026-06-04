<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PromotionalOffer extends Model
{
    use BelongsToTenant;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed_amount';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'package_ids',
        'valid_from',
        'valid_to',
        'is_active',
        'max_redemptions',
        'redemptions_count',
        'terms',
    ];

    protected function casts(): array
    {
        return [
            'package_ids' => 'array',
            'discount_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
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

        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function appliesToPackage(?int $packageId): bool
    {
        $ids = $this->package_ids;
        if (! is_array($ids) || $ids === []) {
            return true;
        }

        return $packageId !== null && in_array($packageId, array_map('intval', $ids), true);
    }
}
