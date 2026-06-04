<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDeviceLoan extends Model
{
    use BelongsToTenant;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'tenant_id',
        'device_id',
        'customer_id',
        'issued_by',
        'returned_by',
        'status',
        'condition_out',
        'condition_in',
        'issued_at',
        'due_return_at',
        'returned_at',
        'issue_notes',
        'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_return_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
