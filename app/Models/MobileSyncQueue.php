<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSyncQueue extends Model
{
    protected $table = 'mobile_sync_queue';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'device_uuid',
        'action',
        'payload',
        'idempotency_key',
        'status',
        'error',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
