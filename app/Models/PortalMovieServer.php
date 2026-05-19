<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PortalMovieServer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'note',
        'sort',
        'is_active',
        'show_on_landing',
        'show_on_portal',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
            'show_on_landing' => 'boolean',
            'show_on_portal' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLanding(Builder $query): Builder
    {
        return $query->active()->where('show_on_landing', true);
    }

    public function scopeForPortal(Builder $query): Builder
    {
        return $query->active()->where('show_on_portal', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }

    public function displayUrl(): string
    {
        return $this->url;
    }
}
