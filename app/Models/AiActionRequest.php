<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\CreatesFromTrustedSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionRequest extends Model
{
    use BelongsToTenant, CreatesFromTrustedSource;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'action_type',
        'status',
        'requested_by',
        'approved_by',
        'rejected_by',
        'summary',
        'payload',
        'preview',
        'rejection_reason',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
