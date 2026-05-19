<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = [
        'general' => 'General',
        'status_change' => 'Status change',
        'billing' => 'Billing',
        'network' => 'Network',
        'kyc' => 'KYC',
        'system' => 'System',
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'category',
        'body',
        'meta',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_pinned' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
