<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'asset_code',
        'name',
        'category',
        'serial_number',
        'purchased_at',
        'purchase_value',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'purchase_value' => 'decimal:2',
        ];
    }
}
