<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ResellerApiKey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'name',
        'key_prefix',
        'key_hash',
        'abilities',
        'rate_limit_per_minute',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ResellerApiUsageLog::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @return array{model: self, plain: string}
     */
    public static function generate(Reseller $reseller, string $name, ?array $abilities = null): array
    {
        $plain = 'rsk_'.Str::random(40);
        $prefix = substr($plain, 0, 12);

        $model = static::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'abilities' => $abilities,
            'rate_limit_per_minute' => $reseller->api_rate_limit_per_minute,
            'is_active' => true,
        ]);

        return ['model' => $model, 'plain' => $plain];
    }

    public static function findByPlainKey(string $plain): ?self
    {
        $prefix = substr($plain, 0, 12);
        if ($prefix === '') {
            return null;
        }

        $key = static::query()
            ->where('key_prefix', $prefix)
            ->where('is_active', true)
            ->first();

        if ($key === null || ! hash_equals($key->key_hash, hash('sha256', $plain))) {
            return null;
        }

        return $key->isValid() ? $key : null;
    }
}
