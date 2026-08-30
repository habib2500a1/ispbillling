<?php

namespace App\Models\Concerns;

use App\Services\Saas\SaasContext;

trait ScopesByCustomerTenant
{
    public static function bootScopesByCustomerTenant(): void
    {
        static::addGlobalScope('saas_tenant', function ($query) {
            $model = $query->getModel();
            $column = $model->getTable().'.'.$model->saasCustomerKey();
            SaasContext::constrainToTenantCustomers($query, $column);
        });
    }

    public function saasCustomerKey(): string
    {
        return 'customer_unique_id';
    }
}
