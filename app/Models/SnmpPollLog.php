<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpPollLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'device_id',
        'poll_type',
        'success',
        'gpon_profile',
        'sys_uptime_ticks',
        'interfaces_total',
        'interfaces_up',
        'onus_online',
        'onus_offline',
        'pon_ports',
        'error_message',
        'metrics',
        'polled_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metrics' => 'array',
            'polled_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
