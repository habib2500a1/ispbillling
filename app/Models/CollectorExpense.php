<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorExpense extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'collector_id',
        'branch_id',
        'category_id',
        'expense_number',
        'amount',
        'status',
        'expense_date',
        'description',
        'proof_path',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CollectorExpenseCategory::class, 'category_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateNumber(int $tenantId): string
    {
        $prefix = 'EXP-'.now()->format('Y').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('expense_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('expense_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
