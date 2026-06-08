<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'organization_type',
        'domain',
        'address',
        'contact_phone',
        'contact_email',
        'logo_path',
        'branding',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'branding' => 'array',
            'settings' => 'array',
        ];
    }

    public function organizationTypeLabel(): string
    {
        return match ($this->organization_type) {
            'multi_isp' => 'Multi ISP',
            'multi_branch' => 'Multi Branch',
            'franchise' => 'Franchise ISP',
            'reseller_isp' => 'Reseller ISP',
            default => 'Single ISP',
        };
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
