<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\CreatesFromTrustedSource;
use Illuminate\Database\Eloquent\Model;

class AiInteractionLog extends Model
{
    use BelongsToTenant, CreatesFromTrustedSource;

    protected $fillable = [
        'tenant_id',
        'channel',
        'actor_type',
        'actor_id',
        'locale',
        'query',
        'reply',
        'tool',
        'domain',
        'latency_ms',
        'llm_used',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'llm_used' => 'boolean',
            'meta' => 'array',
        ];
    }
}
