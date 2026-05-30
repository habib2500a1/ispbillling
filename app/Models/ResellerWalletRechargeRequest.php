<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerWalletRechargeRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'request_number',
        'amount',
        'payment_method',
        'reference',
        'status',
        'gateway',
        'gateway_transaction_id',
        'checkout_order_id',
        'balance_transfer_id',
        'notes',
        'rejection_reason',
        'submitted_by_staff_id',
        'reviewed_by',
        'reviewed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function balanceTransfer(): BelongsTo
    {
        return $this->belongsTo(ResellerBalanceTransfer::class, 'balance_transfer_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(ResellerStaff::class, 'submitted_by_staff_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function generateNumber(int $tenantId): string
    {
        $prefix = 'RWRC-'.now()->format('ym').'-';
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('request_number');
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Pending approval',
        };
    }
}
