<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffSecuritySetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'ip_restriction_enabled',
        'allowed_ips',
        'require_two_factor',
    ];

    protected function casts(): array
    {
        return [
            'ip_restriction_enabled' => 'boolean',
            'allowed_ips' => 'array',
            'require_two_factor' => 'boolean',
        ];
    }
}
