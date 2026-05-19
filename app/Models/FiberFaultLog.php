<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberFaultLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'olt_id',
        'olt_port_id',
        'fault_type',
        'severity',
        'affected_onu_count',
        'affected_customer_count',
        'description',
        'affected_zones',
        'detected_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'affected_zones' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
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
