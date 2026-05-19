<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalAlert extends Model
{
    use BelongsToTenant;

    public const TYPE_LOW_RX = 'low_rx';

    public const TYPE_HIGH_RX = 'high_rx';

    public const TYPE_HIGH_TX = 'high_tx';

    public const TYPE_TX_ABNORMAL = 'tx_abnormal';

    public const TYPE_SUDDEN_DROP = 'sudden_drop';

    public const TYPE_FIBER_CUT = 'fiber_cut';

    public const TYPE_MASS_OFFLINE = 'mass_offline';

    public const TYPE_LOS = 'los';

    protected $fillable = [
        'tenant_id',
        'device_id',
        'olt_id',
        'olt_port_id',
        'customer_id',
        'support_ticket_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'rx_power_dbm',
        'tx_power_dbm',
        'status',
        'detected_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rx_power_dbm' => 'decimal:3',
            'tx_power_dbm' => 'decimal:3',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }
}
