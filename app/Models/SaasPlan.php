<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'monthly_price',
        'yearly_price',
        'per_user_rate',
        'max_customers',
        'max_olts',
        'max_onus',
        'max_routers',
        'max_staff',
        'modules',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function operators(): HasMany
    {
        return $this->hasMany(SaasOperator::class);
    }

    public function priceFor(string $cycle): int
    {
        return $cycle === 'yearly' ? (int) $this->yearly_price : (int) $this->monthly_price;
    }
}
