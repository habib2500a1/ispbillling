<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSaasOperator;
use App\Services\Saas\SaasContext;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use BelongsToSaasOperator;

    protected $fillable = [
        'saas_operator_id',
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

    public static function named(string $name, mixed $tenantId = false): ?self
    {
        if ($tenantId === false) {
            $tenantId = SaasContext::operatorId();
        }

        $query = static::query()->withoutGlobalScope('saas_tenant')->where('template_name', $name);
        if ($tenantId) {
            $query->where('saas_operator_id', (int) $tenantId);
        } else {
            $query->whereNull('saas_operator_id');
        }

        return $query->first();
    }
}
