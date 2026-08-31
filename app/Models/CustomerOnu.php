<?php

namespace App\Models;

use App\Services\Saas\SaasContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOnu extends Model
{
    protected $fillable = [
        'customers_info_id',
        'olt_id',
        'olt_name',
        'pon_port',
        'mac_address',
        'serial_number',
        'rx_power_dbm',
        'tx_power_dbm',
        'oper_status',
        'source',
        'external_id',
        'last_polled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rx_power_dbm' => 'decimal:3',
            'tx_power_dbm' => 'decimal:3',
            'last_polled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomersInfo::class, 'customers_info_id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function rxHistories(): HasMany
    {
        return $this->hasMany(CustomerOnuRxHistory::class)->orderByDesc('recorded_at');
    }

    /**
     * Limit ONU rows to the current SaaS tenant / platform owner view.
     */
    public function scopeForViewer($query)
    {
        $mode = SaasContext::tenantScopeMode();
        if ($mode === 'all') {
            return $query;
        }

        return $query->whereHas('customer');
    }
}
