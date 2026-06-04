<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'mikrotik_server_id',
        'mikrotik_profile_name',
        'mikrotik_synced_at',
        'mikrotik_sync_meta',
        'btrc_label',
        'btrc_bandwidth',
        'btrc_notes',
        'name',
        'type',
        'pricing_model',
        'download_mbps',
        'upload_mbps',
        'included_data_gb',
        'overage_price_per_gb',
        'time_quota_hours',
        'price_monthly',
        'setup_fee',
        'vat_percent',
        'sd_percent',
        'withholding_percent',
        'billing_cycle_days',
        'billing_cycle_type',
        'is_active',
        'show_on_website',
        'is_ott',
        'promo_starts_at',
        'promo_ends_at',
        'promo_price_monthly',
        'slab_pricing',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_ott' => 'boolean',
            'is_active' => 'boolean',
            'show_on_website' => 'boolean',
            'price_monthly' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'sd_percent' => 'decimal:2',
            'withholding_percent' => 'decimal:2',
            'included_data_gb' => 'decimal:2',
            'overage_price_per_gb' => 'decimal:2',
            'promo_starts_at' => 'date',
            'promo_ends_at' => 'date',
            'promo_price_monthly' => 'decimal:2',
            'slab_pricing' => 'array',
            'mikrotik_synced_at' => 'datetime',
            'mikrotik_sync_meta' => 'array',
        ];
    }

    /**
     * Packages shown on public landing + customer portal (admin can still assign hidden packages).
     */
    public function scopePublicCatalog(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)->where('show_on_website', true);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function mikrotikServer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MikrotikServer::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(PackageAddon::class)->orderBy('sort_order');
    }

    public function areaPrices(): HasMany
    {
        return $this->hasMany(PackageAreaPrice::class);
    }

    public function zonePrices(): HasMany
    {
        return $this->hasMany(PackageZonePrice::class);
    }

    public function resellerPackages(): HasMany
    {
        return $this->hasMany(ResellerPackage::class);
    }

    /**
     * Admin/reseller UI label: billing name + MikroTik profile code + price.
     */
    public function adminSelectLabel(): string
    {
        $name = trim((string) $this->name);
        $profile = trim((string) ($this->mikrotik_profile_name ?? ''));
        $price = number_format((float) $this->price_monthly, 0);

        if ($profile !== '' && strcasecmp($profile, $name) !== 0) {
            return "{$name} · {$profile} · {$price} BDT/mo";
        }

        return "{$name} · {$price} BDT/mo";
    }

    public function profileCode(): ?string
    {
        $profile = trim((string) ($this->mikrotik_profile_name ?? ''));

        return $profile !== '' ? $profile : null;
    }

}
