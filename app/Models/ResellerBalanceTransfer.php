<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerBalanceTransfer extends Model
{
    use BelongsToTenant;

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_COMMISSION_PAYOUT = 'commission_payout';

    public const TYPE_PARENT_SHARE = 'parent_share';

    public const TYPE_SELF_RECHARGE = 'self_recharge';

    protected $fillable = [
        'tenant_id',
        'from_reseller_id',
        'to_reseller_id',
        'amount',
        'transfer_type',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function fromReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'from_reseller_id');
    }

    public function toReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'to_reseller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_CREDIT => 'Credit (top-up)',
            self::TYPE_DEBIT => 'Debit',
            self::TYPE_COMMISSION_PAYOUT => 'Commission payout',
            self::TYPE_PARENT_SHARE => 'Parent revenue share',
            self::TYPE_SELF_RECHARGE => 'Wallet top-up',
            self::TYPE_TRANSFER => 'Balance transfer',
            default => 'Balance transfer',
        };
    }
}
