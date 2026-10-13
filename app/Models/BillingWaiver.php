<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use Illuminate\Database\Eloquent\Model;

class BillingWaiver extends Model
{
    use BelongsToSaasOperator;

    protected $fillable = [
        'saas_operator_id',
        'customer_unique_id',
        'amount',
        'reason',
        'waived_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_unique_id', 'customer_unique_id');
    }

    public function waivedBy()
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}
