<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorDailyClosing extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'collector_id',
        'branch_id',
        'closing_date',
        'collected_total',
        'deposited_total',
        'expense_total',
        'declared_cash_in_hand',
        'computed_due',
        'cash_variance',
        'status',
        'notes',
        'submitted_by',
        'approved_by',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'collected_total' => 'decimal:2',
            'deposited_total' => 'decimal:2',
            'expense_total' => 'decimal:2',
            'declared_cash_in_hand' => 'decimal:2',
            'computed_due' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
