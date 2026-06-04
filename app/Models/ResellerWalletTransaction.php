<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerWalletTransaction extends Model
{
    use BelongsToTenant;

    public const WALLET_MAIN = 'main';

    public const WALLET_BONUS = 'bonus';

    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'wallet_type',
        'direction',
        'amount',
        'balance_after',
        'transaction_type',
        'reference',
        'notes',
        'meta',
        'related_transfer_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function relatedTransfer(): BelongsTo
    {
        return $this->belongsTo(ResellerBalanceTransfer::class, 'related_transfer_id');
    }
}
