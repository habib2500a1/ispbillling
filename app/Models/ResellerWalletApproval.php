<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Model;

class ResellerWalletApproval extends Model
{
    use BelongsToSaasOperator;

    protected $fillable = [
        'saas_operator_id',
        'reseller_id',
        'amount',
        'payment_method',
        'payment_reference',
        'note',
        'status',
        'admin_note',
        'processed_by',
        'wallet_transaction_id',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function reseller()
    {
        return $this->belongsTo(Reseller::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(ResellerWalletTransaction::class);
    }
}
