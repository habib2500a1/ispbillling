<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'template',
        'template_name',
        'display_name',
        'event_key',
        'placeholders',
        'sort_order',
        'is_active',
        'template_ex_en',
        'template_ex_bn',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'placeholders' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function label(): string
    {
        return $this->display_name ?: str_replace('_', ' ', (string) $this->template_name);
    }
}
