<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiKnowledgeDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category',
        'title',
        'content',
        'locale',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
