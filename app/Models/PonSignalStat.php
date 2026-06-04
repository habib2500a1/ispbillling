<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Keep only the newest poll row per OLT card/PON (avoids duplicate history in NOC tables).
     */
    public function scopeLatestPerPort(Builder $query, int $tenantId): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->whereIn($table.'.id', function ($sub) use ($tenantId, $table): void {
            $sub->selectRaw('MAX(id)')
                ->from($table)
                ->where('tenant_id', $tenantId)
                ->groupBy('olt_id', 'card_no', 'pon_no');
        });
    }
}
