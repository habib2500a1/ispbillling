<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltPort extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'device_id',
        'card_index',
        'pon_index',
        'label',
        'admin_status',
        'oper_status',
        'utilization_percent',
        'fiber_distance_m',
        'service_profile',
        'last_polled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'card_index' => 'integer',
            'pon_index' => 'integer',
            'utilization_percent' => 'decimal:2',
            'fiber_distance_m' => 'integer',
            'last_polled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OltPort $port): void {
            if (blank($port->label)) {
                $port->label = $port->card_index.'/'.$port->pon_index;
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Device::class, 'olt_port_id');
    }
}
