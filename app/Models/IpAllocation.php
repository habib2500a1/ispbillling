<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAllocation extends Model
{
    use BelongsToTenant;

    public const STATUS_FREE = 'free';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RESERVED = 'reserved';

    protected $fillable = [
        'tenant_id',
        'ip_pool_id',
        'ip_address',
        'customer_id',
        'status',
        'assigned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function ipPool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
