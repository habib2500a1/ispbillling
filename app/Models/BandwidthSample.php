<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthSample extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'mikrotik_server_id',
        'device_id',
        'session_key',
        'username',
        'bytes_in',
        'bytes_out',
        'rate_in_bps',
        'rate_out_bps',
        'framed_ip',
        'caller_id',
        'sampled_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'rate_in_bps' => 'integer',
            'rate_out_bps' => 'integer',
            'sampled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }
}
