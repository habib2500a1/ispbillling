<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCashEntry extends Model
{
    protected $fillable = [
        'user_id',
        'saas_operator_id',
        'recorded_by',
        'type',
        'amount',
        'entry_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(SaasOperator::class, 'saas_operator_id');
    }
}
