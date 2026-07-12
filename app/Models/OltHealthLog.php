<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltHealthLog extends Model
{
    protected $fillable = [
        'olt_id',
        'snmp_ok',
        'cpu_percent',
        'memory_percent',
        'temperature_c',
        'fan_status',
        'power_supply_status',
        'interfaces_up',
        'interfaces_total',
        'onus_online',
        'onus_offline',
        'pon_ports',
        'sys_uptime_ticks',
        'health_score',
        'metrics',
        'sampled_at',
    ];

    protected function casts(): array
    {
        return [
            'snmp_ok' => 'boolean',
            'metrics' => 'array',
            'sampled_at' => 'datetime',
            'temperature_c' => 'decimal:1',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }
}
