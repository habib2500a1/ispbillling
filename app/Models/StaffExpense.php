<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffExpense extends Model
{
    use BelongsToTenant;

    public const SOURCE_VENDOR = 'vendor';

    public const SOURCE_OFFICE = 'office';

    public const SOURCE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'expense_number',
        'expense_source',
        'vendor_id',
        'category_id',
        'amount',
        'payment_method',
        'status',
        'expense_date',
        'description',
        'proof_path',
        'submitted_by',
        'branch_id',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffExpenseCategory::class, 'category_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNumber(int $tenantId): string
    {
        $prefix = 'SEXP-'.now()->format('Y').'-';
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

    public function sourceLabel(): string
    {
        return config('staff_expenses.sources.'.$this->expense_source, ucfirst((string) $this->expense_source));
    }
}
