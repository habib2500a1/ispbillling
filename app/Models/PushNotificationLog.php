<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationLog extends Model
{
    protected $table = 'push_notifications';

    protected $fillable = [
        'tenant_id',
        'tokenable_type',
        'tokenable_id',
        'app',
        'title',
        'body',
        'data',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
