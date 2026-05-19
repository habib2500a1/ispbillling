<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WanBandwidthSample extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'mikrotik_server_id',
        'interface_name',
        'bytes_in',
        'bytes_out',
        'rate_in_bps',
        'rate_out_bps',
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

    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }
}
