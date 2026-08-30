<?php

namespace App\Services\Saas;

use App\Models\MainSiteData;
use App\Models\SaasOperator;
use App\Models\SaasPlan;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Platform owner sells an ISP admin login. Buyer gets plan quotas, never resell.
 */
final class OperatorProvisioningService
{
    public const OPERATOR_ROLE = 'Operator';

    public const SELL_PERMISSION = 'saas-sell';

    public function __construct(
        private readonly SaasPlanCatalog $catalog,
        private readonly SaasBillingService $billing,
    ) {}

    public function ensureRoles(): Role
    {
        Permission::findOrCreate(self::SELL_PERMISSION, 'web');
        Permission::findOrCreate('staff-cash', 'web');

        foreach (['site-settings', 'site-setup', 'payment-setup'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role = Role::findOrCreate(self::OPERATOR_ROLE, 'web');
        $all = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', self::SELL_PERMISSION)
            ->pluck('name')
            ->all();
        $role->syncPermissions($all);

        return $role;
    }

    /**
     * @param  array{
     *   company: string,
     *   contact_name: string,
     *   email: string,
     *   phone?: string|null,
     *   domain?: string|null,
     *   plan?: string,
     *   billing_cycle?: string,
     *   password: string,
     *   notes?: string|null,
     *   base_amount?: int|null,
     *   per_user_rate?: int|null,
     *   max_customers?: int|null,
     *   max_olts?: int|null,
     *   max_onus?: int|null,
     *   max_routers?: int|null,
     *   max_staff?: int|null,
     *   modules?: list<string>|null
     * }  $data
     */
    public function sell(array $data): SaasOperator
    {
        $this->ensureRoles();
        $this->catalog->seed();

        $slug = $data['plan'] ?? 'standard';
        $plan = SaasPlan::query()->where('slug', $slug)->first() ?? $this->catalog->resolve('standard');
        $requested = $data['billing_cycle'] ?? 'monthly';
        $lifetime = $plan->isLifetime() || $requested === 'lifetime';
        $cycle = $lifetime ? 'lifetime' : ($requested === 'yearly' ? 'yearly' : 'monthly');

        $user = User::create([
            'name' => $data['contact_name'],
            'email' => $data['email'],
            'mobile' => $data['phone'] ?? null,
            'password' => bcrypt($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([self::OPERATOR_ROLE]);

        $operator = SaasOperator::create([
            'user_id' => $user->id,
            'saas_plan_id' => $plan->id,
            'company' => $data['company'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'domain' => $data['domain'] ?? null,
            'plan' => $plan->slug,
            'billing_cycle' => $cycle,
            'base_amount' => $lifetime ? 0 : (int) ($data['base_amount'] ?? $plan->priceFor($cycle)),
            'per_user_rate' => $lifetime ? 0 : (int) ($data['per_user_rate'] ?? $plan->per_user_rate),
            'user_base_count' => 0,
            'amount' => 0,
            'status' => 'active',
            'can_resell' => false,
            'notes' => $data['notes'] ?? null,
            'sold_at' => now(),
            'next_due_at' => $lifetime ? null : ($cycle === 'yearly' ? now()->addYear() : now()->addMonth()),
            'max_customers' => (int) ($data['max_customers'] ?? $plan->max_customers),
            'max_olts' => (int) ($data['max_olts'] ?? $plan->max_olts),
            'max_onus' => (int) ($data['max_onus'] ?? $plan->max_onus),
            'max_routers' => (int) ($data['max_routers'] ?? $plan->max_routers),
            'max_staff' => (int) ($data['max_staff'] ?? $plan->max_staff),
            'modules' => $data['modules'] ?? $plan->modules,
        ]);

        $user->update(['saas_operator_id' => $operator->id]);

        if (! empty($operator->domain)) {
            try {
                app(CaddyDomainSync::class)->sync();
            } catch (\Throwable $e) {
                // Ignore when Caddy is not in this environment.
            }
        }

        $this->seedOperatorSetup($operator);

        if (! $lifetime) {
            $this->billing->quote($operator);
            $this->billing->issueInvoice($operator, $operator->next_due_at);
        }

        return $operator->fresh(['user', 'planCatalog', 'invoices']);
    }

    public function seedOperatorSetup(SaasOperator $operator): void
    {
        $id = (int) $operator->id;
        MainSiteData::setValueForTenant($id, 'site_name', $operator->company);
        MainSiteData::setValueForTenant($id, 'payment_bkash_enabled', 1);
        MainSiteData::setValueForTenant($id, 'payment_nagad_enabled', 1);
        MainSiteData::setValueForTenant($id, 'payment_sslcommerz_enabled', 1);
        MainSiteData::setValueForTenant($id, 'payment_bkash_sandbox', 0);
        MainSiteData::setValueForTenant($id, 'payment_bkash_base_url', \App\Http\Controllers\Payment\BkashPaymentController::LIVE_URL);
    }

    public function setStatus(SaasOperator $operator, string $status): void
    {
        if ($status === 'active') {
            $this->billing->unlock($operator);

            return;
        }

        if ($status === 'locked') {
            $this->billing->lock($operator, 'manual');

            return;
        }

        $this->billing->suspend($operator);
    }

    public function applyPlan(SaasOperator $operator, SaasPlan $plan, string $cycle): void
    {
        $lifetime = $plan->isLifetime() || $cycle === 'lifetime';
        $normalized = $lifetime ? 'lifetime' : ($cycle === 'yearly' ? 'yearly' : 'monthly');

        $operator->update([
            'saas_plan_id' => $plan->id,
            'plan' => $plan->slug,
            'billing_cycle' => $normalized,
            'base_amount' => $lifetime ? 0 : $plan->priceFor($normalized),
            'per_user_rate' => $lifetime ? 0 : (int) $plan->per_user_rate,
            'max_customers' => $plan->max_customers,
            'max_olts' => $plan->max_olts,
            'max_onus' => $plan->max_onus,
            'max_routers' => $plan->max_routers,
            'max_staff' => $plan->max_staff,
            'modules' => $plan->modules,
        ]);

        if ($lifetime) {
            $this->grantLifetime($operator->fresh());

            return;
        }

        $fresh = $operator->fresh();
        if (! $fresh->next_due_at) {
            $fresh->update([
                'next_due_at' => $normalized === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);
        }

        $this->billing->quote($fresh->fresh());
    }

    /**
     * @param  array{
     *   company: string,
     *   contact_name: string,
     *   email: string,
     *   phone?: string|null,
     *   notes?: string|null,
     *   password?: string|null
     * }  $data
     */
    public function updateProfile(SaasOperator $operator, array $data): SaasOperator
    {
        $user = $operator->user;
        $email = trim((string) $data['email']);

        $operator->update([
            'company' => trim((string) $data['company']),
            'contact_name' => trim((string) $data['contact_name']),
            'email' => $email,
            'phone' => $data['phone'] ?? $operator->phone,
            'notes' => $data['notes'] ?? $operator->notes,
        ]);

        if ($user) {
            $payload = [
                'name' => trim((string) $data['contact_name']),
                'email' => $email,
                'mobile' => $data['phone'] ?? $user->mobile,
            ];
            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }
            $user->update($payload);
        }

        return $operator->fresh(['user', 'planCatalog']);
    }

    public function grantLifetime(SaasOperator $operator): void
    {
        $operator->update([
            'billing_cycle' => 'lifetime',
            'base_amount' => 0,
            'per_user_rate' => 0,
            'amount' => 0,
            'next_due_at' => null,
            'status' => 'active',
            'locked_at' => null,
            'lock_reason' => null,
        ]);

        $operator->invoices()->where('status', '!=', 'paid')->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_note' => 'lifetime',
        ]);
    }
}
