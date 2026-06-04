<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerCustomerTransfer extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'from_reseller_id',
        'to_reseller_id',
        'requested_by_reseller_id',
        'approved_by',
        'status',
        'reason',
        'admin_notes',
        'requested_at',
        'approved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'from_reseller_id');
    }

    public function toReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'to_reseller_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'requested_by_reseller_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
