<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOnuRxHistory extends Model
{
    protected $fillable = [
        'customer_onu_id',
        'rx_power_dbm',
        'tx_power_dbm',
        'source',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'rx_power_dbm' => 'decimal:3',
            'tx_power_dbm' => 'decimal:3',
            'recorded_at' => 'datetime',
        ];
    }

    public function onu(): BelongsTo
    {
        return $this->belongsTo(CustomerOnu::class, 'customer_onu_id');
    }
}
