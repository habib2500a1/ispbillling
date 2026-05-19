<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PonSignalStat extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'olt_id',
        'olt_port_id',
        'card_no',
        'pon_no',
        'onu_total',
        'onu_online',
        'onu_offline',
        'onu_critical',
        'onu_warning',
        'avg_rx_dbm',
        'min_rx_dbm',
        'max_rx_dbm',
        'fault_percent',
        'computed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'avg_rx_dbm' => 'decimal:3',
            'min_rx_dbm' => 'decimal:3',
            'max_rx_dbm' => 'decimal:3',
            'fault_percent' => 'decimal:2',
            'computed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'olt_id');
    }

    public function oltPort(): BelongsTo
    {
        return $this->belongsTo(OltPort::class);
    }
}
