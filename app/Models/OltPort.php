<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltPort extends Model
{
    protected $fillable = [
        'olt_id',
        'card_index',
        'pon_index',
        'label',
        'admin_status',
        'oper_status',
        'utilization_percent',
        'last_polled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_polled_at' => 'datetime',
            'utilization_percent' => 'decimal:2',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }
}
