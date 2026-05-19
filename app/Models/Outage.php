<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'title',
        'description',
        'started_at',
        'ended_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function scopeCurrentlyActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where('started_at', '<=', now())
            ->where(function (Builder $b): void {
                $b->whereNull('ended_at')->orWhere('ended_at', '>', now());
            });
    }

    public function scopeForCustomerArea(Builder $q, ?int $areaId): Builder
    {
        return $q->where(function (Builder $b) use ($areaId): void {
            $b->whereNull('area_id');
            if ($areaId !== null) {
                $b->orWhere('area_id', $areaId);
            }
        });
    }
}
