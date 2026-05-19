<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorSettlement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'settlement_number',
        'collector_id',
        'branch_id',
        'submitted_by',
        'approved_by',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'proof_path',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cashbook_entry_id',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashbookEntry(): BelongsTo
    {
        return $this->belongsTo(CashbookEntry::class);
    }

    public static function generateNumber(int $tenantId): string
    {
        $prefix = 'SET-'.now()->format('Y').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('settlement_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('settlement_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
