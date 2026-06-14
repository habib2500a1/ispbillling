<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyPortalMirrorRecord extends Model
{
    protected $fillable = [
        'legacy_portal_mirror_run_id',
        'tenant_id',
        'domain',
        'source_key',
        'method',
        'url',
        'request',
        'http_status',
        'content_type',
        'checksum',
        'payload_json',
        'payload_text',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'payload_json' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LegacyPortalMirrorRun::class, 'legacy_portal_mirror_run_id');
    }
}
