<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotVoucher extends Model
{
    use BelongsToTenant;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'code',
        'batch_name',
        'duration_hours',
        'data_limit_mb',
        'price',
        'status',
        'package_id',
        'mikrotik_server_id',
        'hotspot_username',
        'hotspot_password',
        'customer_id',
        'used_at',
        'provisioned_at',
        'provision_error',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'data_limit_mb' => 'integer',
            'price' => 'decimal:2',
            'used_at' => 'datetime',
            'provisioned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function mikrotikServer(): BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
