<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikSessionAlert extends Model
{
    use BelongsToTenant;

    public const TYPE_MULTI_ROUTER = 'multi_router_online';

    public const TYPE_WRONG_ROUTER = 'wrong_router_online';

    public const TYPE_OVERDUE_ONLINE = 'overdue_still_online';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'alert_type',
        'severity',
        'login',
        'message',
        'meta',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
