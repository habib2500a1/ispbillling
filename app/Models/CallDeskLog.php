<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallDeskLog extends Model
{
    public const OUTCOMES = [
        'answered' => 'Answered',
        'no_answer' => 'No answer',
        'busy' => 'Busy',
        'callback' => 'Callback requested',
        'wrong_number' => 'Wrong number',
        'disconnected' => 'Disconnected',
    ];

    protected $fillable = [
        'customer_unique_id',
        'phone',
        'staff_user_id',
        'direction',
        'outcome',
        'duration_seconds',
        'remarks',
        'support_ticket_id',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_unique_id', 'customer_unique_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function getOutcomeLabelAttribute(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst((string) $this->outcome);
    }
}
