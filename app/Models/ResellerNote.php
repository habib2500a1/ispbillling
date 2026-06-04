<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reseller_id',
        'author_user_id',
        'author_reseller_id',
        'body',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function authorReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'author_reseller_id');
    }
}
