<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveRequest extends Model
{
    public const TYPES = [
        'casual' => 'Casual',
        'sick' => 'Sick',
        'unpaid' => 'Unpaid',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'user_id',
        'from_date',
        'to_date',
        'leave_type',
        'status',
        'reason',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getDaysAttribute(): int
    {
        return max(1, $this->from_date->diffInDays($this->to_date) + 1);
    }
}
