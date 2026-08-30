<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasOperator extends Model
{
    protected $fillable = [
        'user_id',
        'company',
        'contact_name',
        'email',
        'phone',
        'plan',
        'status',
        'can_resell',
        'notes',
        'sold_at',
    ];

    protected $casts = [
        'can_resell' => 'boolean',
        'sold_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
