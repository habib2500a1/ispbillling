<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'name',
        'template_type',
        'event_key',
        'body',
        'placeholders',
        'is_enabled',
        'voice_enabled',
        'voice_template_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_enabled' => 'boolean',
            'voice_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function voiceTemplate(): BelongsTo
    {
        return $this->belongsTo(VoiceTemplate::class);
    }

    public static function findByKey(string $key, ?int $tenantId = null): ?self
    {
        $tenantId = $tenantId ?? (int) (auth()->user()?->tenant_id ?? 1);

        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($key): void {
                $q->where('key', $key)->orWhere('event_key', $key);
            })
            ->orderByRaw('CASE WHEN `key` = ? THEN 0 ELSE 1 END', [$key])
            ->orderByDesc('id')
            ->first();
    }
}
