<?php

namespace App\Support;

use App\Models\Customer;

final class WebhookCustomerResolver
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolve(array $data, ?int $tenantId = null): ?Customer
    {
        $tenantId ??= PublicTenantContext::tenantId();

        if (! empty($data['customer_id'])) {
            return Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($data['customer_id'])
                ->first();
        }

        if (! empty($data['customer_code'])) {
            return Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_code', $data['customer_code'])
                ->first();
        }

        if (! empty($data['phone'])) {
            $digits = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';

            return Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($data, $digits): void {
                    $q->where('phone', $data['phone']);
                    if ($digits !== '') {
                        $q->orWhere('phone', $digits);
                    }
                })
                ->first();
        }

        return null;
    }
}
