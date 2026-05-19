<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsDeliveryReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'notification_log_id',
        'gateway',
        'gateway_message_id',
        'recipient',
        'delivery_status',
        'status_text',
        'payload',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class);
    }
}
