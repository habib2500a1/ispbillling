<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnuSignalLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'device_id',
        'olt_id',
        'olt_port_id',
        'rx_power_dbm',
        'tx_power_dbm',
        'raw_rx_power_dbm',
        'raw_tx_power_dbm',
        'temperature_c',
        'voltage_v',
        'is_spike',
        'poll_source',
        'rx_level',
        'tx_level',
        'onu_oper_status',
        'health_score',
        'granularity',
        'sampled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rx_power_dbm' => 'decimal:3',
            'tx_power_dbm' => 'decimal:3',
            'raw_rx_power_dbm' => 'decimal:3',
            'raw_tx_power_dbm' => 'decimal:3',
            'temperature_c' => 'decimal:2',
            'voltage_v' => 'decimal:3',
            'is_spike' => 'boolean',
            'health_score' => 'integer',
            'sampled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
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
