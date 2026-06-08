<?php

namespace App\Services\Tenant;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\TenantSubscriptionCatalog;
use Illuminate\Validation\ValidationException;

final class TenantSubscriptionService
{
    /**
     * @return array<string, mixed>
     */
    public function forTenant(?int $tenantId): array
    {
        $tenantId = $tenantId ?? 1;
        $tenant = Tenant::query()->find($tenantId);
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        $sub = is_array($settings['subscription'] ?? null) ? $settings['subscription'] : [];

        $planKey = (string) ($sub['plan_key'] ?? TenantSubscriptionCatalog::PLAN_STARTER_100);
        $catalog = TenantSubscriptionCatalog::plans()[$planKey] ?? TenantSubscriptionCatalog::plans()[TenantSubscriptionCatalog::PLAN_STARTER_100];

        $maxCustomers = array_key_exists('max_customers', $sub)
            ? ($sub['max_customers'] === null ? null : (int) $sub['max_customers'])
            : $catalog['max_customers'];

        $used = Customer::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant?->name ?? 'Tenant',
            'plan_key' => $planKey,
            'plan_name' => (string) ($sub['plan_name'] ?? $catalog['label']),
            'max_customers' => $maxCustomers,
            'monthly_fee_bdt' => (float) ($sub['monthly_fee_bdt'] ?? $catalog['monthly_fee_bdt']),
            'billing_day' => max(1, min(28, (int) ($sub['billing_day'] ?? 1))),
            'status' => (string) ($sub['status'] ?? 'active'),
            'notes' => (string) ($sub['notes'] ?? ''),
            'customers_used' => $used,
            'customers_remaining' => $maxCustomers === null ? null : max(0, $maxCustomers - $used),
            'at_limit' => $maxCustomers !== null && $used >= $maxCustomers,
            'usage_percent' => $maxCustomers ? min(100, (int) round(($used / max(1, $maxCustomers)) * 100)) : null,
        ];
    }

    public function canAddCustomer(?int $tenantId): bool
    {
        $sub = $this->forTenant($tenantId);

        if (in_array($sub['status'], ['suspended', 'cancelled'], true)) {
            return false;
        }

        if ($sub['max_customers'] === null) {
            return true;
        }

        return (int) $sub['customers_used'] < (int) $sub['max_customers'];
    }

    public function assertCanAddCustomer(?int $tenantId): void
    {
        $sub = $this->forTenant($tenantId);

        if (in_array($sub['status'], ['suspended', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'tenant' => "Platform subscription is {$sub['status']}. Contact your provider to renew.",
            ]);
        }

        if (! $this->canAddCustomer($tenantId)) {
            throw ValidationException::withMessages([
                'tenant' => "Customer limit reached ({$sub['customers_used']}/{$sub['max_customers']}). Upgrade the SaaS package to add more subscribers.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeSubscriptionInput(array $input): array
    {
        $planKey = (string) ($input['plan_key'] ?? TenantSubscriptionCatalog::PLAN_STARTER_100);
        $defaults = TenantSubscriptionCatalog::defaultsForPlan(
            $planKey,
            (int) ($input['billing_day'] ?? 1),
        );

        if ($planKey === TenantSubscriptionCatalog::PLAN_CUSTOM) {
            $defaults['max_customers'] = isset($input['max_customers']) && $input['max_customers'] !== ''
                ? (int) $input['max_customers']
                : null;
            $defaults['monthly_fee_bdt'] = (float) ($input['monthly_fee_bdt'] ?? 0);
            $defaults['plan_name'] = 'Custom — '.($defaults['max_customers'] ?? '∞').' customers';
        }

        return array_merge($defaults, [
            'billing_day' => max(1, min(28, (int) ($input['billing_day'] ?? $defaults['billing_day']))),
            'status' => (string) ($input['status'] ?? 'active'),
            'notes' => (string) ($input['notes'] ?? ''),
        ]);
    }

    public function applySubscription(Tenant $tenant, array $subscription): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['subscription'] = $this->normalizeSubscriptionInput($subscription);
        $tenant->forceFill(['settings' => $settings])->save();
    }
}
