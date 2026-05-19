<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnuHealthScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'device_id',
        'health_score',
        'stability_score',
        'rx_level',
        'root_cause_hint',
        'rx_trend_dbm',
        'smoothed_rx_dbm',
        'smoothed_tx_dbm',
        'rx_stddev_db',
        'fiber_health_score',
        'computed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'health_score' => 'integer',
            'stability_score' => 'integer',
            'rx_trend_dbm' => 'decimal:3',
            'smoothed_rx_dbm' => 'decimal:3',
            'smoothed_tx_dbm' => 'decimal:3',
            'rx_stddev_db' => 'decimal:3',
            'fiber_health_score' => 'integer',
            'computed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
