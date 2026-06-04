<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceSmsCampaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'voice_template_id',
        'name',
        'send_sms',
        'send_voice',
        'status',
        'scheduled_at',
        'targets_count',
        'sent_count',
        'failed_count',
        'voice_sent_count',
        'voice_failed_count',
        'target_filters',
    ];

    protected function casts(): array
    {
        return [
            'send_sms' => 'boolean',
            'send_voice' => 'boolean',
            'scheduled_at' => 'datetime',
            'target_filters' => 'array',
        ];
    }

    public function voiceTemplate(): BelongsTo
    {
        return $this->belongsTo(VoiceTemplate::class);
    }
}
