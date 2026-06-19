<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAssignmentRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'pop_box_id',
        'department',
        'skill_tag',
        'vip_priority',
        'max_open_tickets',
        'user_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'vip_priority' => 'boolean',
            'max_open_tickets' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function popBox(): BelongsTo
    {
        return $this->belongsTo(PopBox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
