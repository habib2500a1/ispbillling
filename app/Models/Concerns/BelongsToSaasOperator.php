<?php

namespace App\Models\Concerns;

use App\Services\Saas\SaasContext;
use Illuminate\Support\Facades\Schema;

trait BelongsToSaasOperator
{
    public static function bootBelongsToSaasOperator(): void
    {
        static::addGlobalScope('saas_tenant', function ($query) {
            $table = $query->getModel()->getTable();
            if (! Schema::hasColumn($table, 'saas_operator_id')) {
                return;
            }

            $column = $table.'.saas_operator_id';
            $mode = SaasContext::tenantScopeMode();

            if ($mode === 'all') {
                return;
            }

            if ($mode === 'tenant') {
                $query->where($column, SaasContext::tenantId());

                return;
            }

            $query->whereNull($column);
        });

        static::creating(function ($model) {
            if (! Schema::hasColumn($model->getTable(), 'saas_operator_id')) {
                return;
            }

            if (! empty($model->saas_operator_id)) {
                return;
            }

            $id = SaasContext::operatorId();
            if ($id) {
                $model->saas_operator_id = $id;
            }
        });
    }
}
