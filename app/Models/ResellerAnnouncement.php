<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerAnnouncement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'title',
        'body',
        'audience',
        'target_reseller_ids',
        'priority',
        'published_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_reseller_ids' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ResellerAnnouncementRead::class);
    }

    public function isVisibleTo(Reseller $reseller): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->audience === 'all') {
            return (int) $this->tenant_id === (int) $reseller->tenant_id;
        }

        $targets = $this->target_reseller_ids ?? [];

        return in_array($reseller->id, $targets, true);
    }
}
