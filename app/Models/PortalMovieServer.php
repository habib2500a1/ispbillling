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

    public function linkScheme(): ?string
    {
        $scheme = parse_url($this->resolvedUrl(), PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? strtolower($scheme) : null;
    }

    public function displayHost(): string
    {
        $resolved = $this->resolvedUrl();
        if ($resolved === '#') {
            return '';
        }

        $host = parse_url($resolved, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return preg_replace('#^[a-z][a-z0-9+\-.]*://#i', '', $resolved) ?? $this->url;
    }

    /**
     * Normalized link for browsers (adds ftp:// or https:// when missing).
     */
    public function resolvedUrl(): string
    {
        $url = trim((string) $this->url);
        if ($url === '') {
            return '#';
        }

        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
            return $url;
        }

        $hint = strtolower(trim($this->name.' '.$url));
        $looksLikeFtp = str_contains($hint, 'ftp')
            || str_contains($hint, 'sftp')
            || preg_match('#:\d{2,5}(?:/|$)#', $url);

        if ($looksLikeFtp) {
            return 'ftp://'.ltrim($url, '/');
        }

        return 'https://'.ltrim($url, '/');
    }
}
