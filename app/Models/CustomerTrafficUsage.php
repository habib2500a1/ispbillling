<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTrafficUsage extends Model
{
    use BelongsToSaasOperator;

    protected $fillable = [
        'saas_operator_id',
        'ppp_secret_id',
        'username',
        'router_name',
        'customer_unique_id',
        'session_rx_bytes',
        'session_tx_bytes',
        'session_started_at',
        'last_session_rx_bytes',
        'last_session_tx_bytes',
        'day_key',
        'day_rx_bytes',
        'day_tx_bytes',
        'month_key',
        'month_rx_bytes',
        'month_tx_bytes',
        'prev_rx_bytes',
        'prev_tx_bytes',
        'online',
        'polled_at',
    ];

    protected function casts(): array
    {
        return [
            'session_rx_bytes' => 'integer',
            'session_tx_bytes' => 'integer',
            'last_session_rx_bytes' => 'integer',
            'last_session_tx_bytes' => 'integer',
            'day_rx_bytes' => 'integer',
            'day_tx_bytes' => 'integer',
            'month_rx_bytes' => 'integer',
            'month_tx_bytes' => 'integer',
            'prev_rx_bytes' => 'integer',
            'prev_tx_bytes' => 'integer',
            'online' => 'boolean',
            'session_started_at' => 'datetime',
            'polled_at' => 'datetime',
        ];
    }

    public function pppSecret(): BelongsTo
    {
        return $this->belongsTo(PPPSecrets::class, 'ppp_secret_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_unique_id', 'customer_unique_id');
    }
}
