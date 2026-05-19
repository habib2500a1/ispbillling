<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorCollection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'customer_id',
        'collector_id',
        'branch_id',
        'amount',
        'amount_settled',
        'payment_method',
        'status',
        'collected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_settled' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function outstandingAmount(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->amount_settled, 2));
    }

    public function syncStatus(): void
    {
        $outstanding = $this->outstandingAmount();
        if ($outstanding <= 0.009) {
            $this->status = 'settled';
        } elseif ((float) $this->amount_settled > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'open';
        }
    }
}
