<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyPortalMirrorRun extends Model
{
    protected $fillable = [
        'tenant_id',
        'run_uuid',
        'mode',
        'base_url',
        'status',
        'options',
        'summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(LegacyPortalMirrorRecord::class);
    }
}
